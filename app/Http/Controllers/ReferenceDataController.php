<?php

namespace App\Http\Controllers;

use App\Actions\CreateProgrammeCountyCoverage;
use App\Actions\CreateReferenceDataRelease;
use App\Actions\PublishReferenceDataRelease;
use App\Enums\ProgrammePermission;
use App\Http\Requests\PublishReferenceDataReleaseRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\StoreProgrammeCountyCoverageRequest;
use App\Http\Requests\StoreProgrammeRequest;
use App\Http\Requests\StoreReferenceDataReleaseRequest;
use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Requests\UpdateProgrammeRequest;
use App\Http\Requests\UpdateSectorRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceDataController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData): Response
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        $search = $request->string('search')->trim()->toString();
        /** @var User $user */
        $user = $request->user();
        $workspaceFilters = WorkspaceFilters::fromRequest($request);

        $organizations = Organization::query()->with('county:id,name,code,logo_path,logo_source_authority,logo_verified_at')->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))->latest()->paginate(15, pageName: 'organizations_page')->withQueryString();
        $sectors = Sector::query()->with('parent:id,name,code')->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"))))->latest()->paginate(15, pageName: 'sectors_page')->withQueryString();
        $programmes = Programme::query()->with(['leadOrganization:id,name', 'sector:id,name'])->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))->latest()->paginate(15, pageName: 'programmes_page')->withQueryString();
        $releases = ReferenceDataRelease::query()->with(['submitter:id,name', 'approver:id,name'])->latest('version')->limit(12)->get();

        return Inertia::render('reference-data/index', [
            'filters' => [
                'search' => $search,
                'from' => $workspaceFilters->from,
                'to' => $workspaceFilters->to,
                'county_id' => $workspaceFilters->countyId,
                'sector_id' => $workspaceFilters->sectorId,
                'status' => $workspaceFilters->status,
            ],
            'organizations' => $organizations->through(fn (Organization $organization): array => ['id' => $organization->id, 'code' => $organization->code, 'name' => $organization->name, 'type' => $organization->type, 'county' => $organization->county?->identityCell(), 'email' => $organization->email, 'status' => $organization->status]),
            'sectors' => $sectors->through(fn (Sector $sector): array => ['id' => $sector->id, 'code' => $sector->code, 'name' => $sector->name, 'parent' => $sector->parent ? ['id' => $sector->parent->id, 'code' => $sector->parent->code, 'name' => $sector->parent->name] : null, 'description' => $sector->description, 'isActive' => $sector->is_active]),
            'programmes' => $programmes->through(fn (Programme $programme): array => ['id' => $programme->id, 'code' => $programme->code, 'name' => $programme->name, 'description' => $programme->description, 'organization' => $programme->leadOrganization?->name, 'sector' => $programme->sector?->name, 'startsOn' => $programme->starts_on?->toDateString(), 'endsOn' => $programme->ends_on?->toDateString(), 'status' => $programme->status, 'budgetAmount' => $programme->budget_amount, 'currency' => $programme->currency]),
            'programmeCoverages' => $workspaceData->programmeCountyCoverages($user, $workspaceFilters),
            'releases' => $releases->map(fn (ReferenceDataRelease $release): array => [
                'id' => $release->id, 'version' => $release->version, 'status' => $release->status, 'changeSummary' => $release->change_summary,
                'checksum' => $release->checksum, 'submittedBy' => $release->submitter->name, 'approvedBy' => $release->approver?->name,
                'submittedAt' => $release->submitted_at->toIso8601String(), 'publishedAt' => $release->published_at?->toIso8601String(),
                'effectiveFrom' => $release->effective_from?->toDateString(), 'approvalReference' => $release->approval_reference,
                'counts' => collect($release->snapshot)->map(fn (array $records): int => count($records))->all(),
            ])->values(),
            'capabilities' => ['manage' => $request->user()?->can(ProgrammePermission::ManageReferenceData->value), 'approve' => $request->user()?->can(ProgrammePermission::ApproveReferenceData->value)],
            'options' => [
                'counties' => County::query()->orderBy('code')->get()->map->identityCell()->values(),
                'organizations' => Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
                'programmes' => Programme::query()->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function storeRelease(StoreReferenceDataReleaseRequest $request, CreateReferenceDataRelease $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $create->handle($user, $request->string('change_summary')->toString());

        return $this->success('Canonical reference-data snapshot submitted for independent publication.');
    }

    public function publishRelease(PublishReferenceDataReleaseRequest $request, ReferenceDataRelease $release, PublishReferenceDataRelease $publish): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{approval_reference: string, effective_from: string} $attributes */
        $attributes = $request->validated();
        $publish->handle($release, $user, $attributes);

        return $this->success('Reference-data release independently published.');
    }

    public function storeOrganization(StoreOrganizationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $organization = Organization::create($request->validated());
        $this->audit($request, $auditLogger, $organization, 'reference.organization.created');

        return $this->success('Organization created.');
    }

    public function updateOrganization(UpdateOrganizationRequest $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $organization->update($request->validated());
        $this->audit($request, $auditLogger, $organization, 'reference.organization.updated');

        return $this->success('Organization updated.');
    }

    public function destroyOrganization(Request $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($organization->programmes()->exists(), 409, 'Reassign linked programmes before archiving this organization.');
        $this->audit($request, $auditLogger, $organization, 'reference.organization.archived');
        $organization->delete();

        return $this->success('Organization archived.');
    }

    public function storeSector(StoreSectorRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $sector = Sector::create($request->validated());
        $this->audit($request, $auditLogger, $sector, 'reference.sector.created');

        return $this->success('Sector created.');
    }

    public function updateSector(UpdateSectorRequest $request, Sector $sector, AuditLogger $auditLogger): RedirectResponse
    {
        $sector->update($request->validated());
        $this->audit($request, $auditLogger, $sector, 'reference.sector.updated');

        return $this->success('Sector updated.');
    }

    public function destroySector(Request $request, Sector $sector, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($sector->programmes()->exists(), 409, 'Reassign linked programmes before archiving this sector.');
        abort_if($sector->children()->exists(), 409, 'Reassign child sectors before archiving this sector.');
        $this->audit($request, $auditLogger, $sector, 'reference.sector.archived');
        $sector->delete();

        return $this->success('Sector archived.');
    }

    public function storeProgramme(StoreProgrammeRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $programme = Programme::create($request->validated());
        $this->audit($request, $auditLogger, $programme, 'reference.programme.created');

        return $this->success('Programme created.');
    }

    public function updateProgramme(UpdateProgrammeRequest $request, Programme $programme, AuditLogger $auditLogger): RedirectResponse
    {
        $programme->update($request->validated());
        $this->audit($request, $auditLogger, $programme, 'reference.programme.updated');

        return $this->success('Programme updated.');
    }

    public function destroyProgramme(Request $request, Programme $programme, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($programme->countyCoverages()->exists(), 409, 'Archive programme county coverage before archiving this programme.');
        $this->audit($request, $auditLogger, $programme, 'reference.programme.archived');
        $programme->delete();

        return $this->success('Programme archived.');
    }

    public function storeProgrammeCountyCoverage(StoreProgrammeCountyCoverageRequest $request, CreateProgrammeCountyCoverage $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{programme_id: string, county_id: string, implementation_lead_id?: string|null, starts_on: string, ends_on?: string|null, status: string, funding_allocation?: int|float|string|null, currency: string, source_reference: string, notes?: string|null} $attributes */
        $attributes = $request->validated();
        $create->handle($user, $attributes);

        return $this->success('Programme county coverage created.');
    }

    public function destroyProgrammeCountyCoverage(Request $request, ProgrammeCountyCoverage $programmeCountyCoverage, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $programmeCountyCoverage, 'reference.programme-coverage.archived', 'Programme county coverage archived.', $programmeCountyCoverage->county_id, [
            'programme_id' => $programmeCountyCoverage->programme_id,
            'county_id' => $programmeCountyCoverage->county_id,
            'source_reference' => $programmeCountyCoverage->source_reference,
        ]);
        $programmeCountyCoverage->delete();

        return $this->success('Programme county coverage archived.');
    }

    private function audit(Request $request, AuditLogger $auditLogger, Organization|Sector|Programme $subject, string $action): void
    {
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $subject, $action, "{$subject->name} reference data changed.");
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
