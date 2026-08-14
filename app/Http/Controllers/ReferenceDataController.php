<?php

namespace App\Http\Controllers;

use App\Actions\BulkArchiveCounties;
use App\Actions\CreateProgrammeCountyCoverage;
use App\Actions\CreateReferenceDataRelease;
use App\Actions\PublishReferenceDataRelease;
use App\Enums\ProgrammePermission;
use App\Http\Requests\BulkArchiveCountiesRequest;
use App\Http\Requests\PublishReferenceDataReleaseRequest;
use App\Http\Requests\StoreCountyRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\StoreProgrammeCountyCoverageRequest;
use App\Http\Requests\StoreProgrammeRequest;
use App\Http\Requests\StoreReferenceDataReleaseRequest;
use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateCountyRequest;
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
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Services\AuditLogger;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

        $counties = County::query()
            ->withCount(['users', 'assessments', 'documents', 'grants', 'programmeCoverages'])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('region', 'ilike', "%{$search}%")->orWhereRaw('CAST(code AS TEXT) ILIKE ?', ["%{$search}%"])))
            ->orderBy('code')
            ->paginate(15, pageName: 'counties_page')
            ->withQueryString();
        $organizations = Organization::query()->with('county:id,name,code,logo_path,logo_source_authority,logo_verified_at')->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))->latest()->paginate(15, pageName: 'organizations_page')->withQueryString();
        $sectors = Sector::query()->with('parent:id,name,code')->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"))))->latest()->paginate(15, pageName: 'sectors_page')->withQueryString();
        $programmes = Programme::query()->with(['leadOrganization:id,name', 'sector:id,name'])->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))->latest()->paginate(15, pageName: 'programmes_page')->withQueryString();
        $releases = ReferenceDataRelease::query()->with(['submitter:id,name', 'approver:id,name'])->latest('version')->limit(12)->get();
        $subCounties = SubCounty::query()
            ->with('county:id,name,code,logo_path,logo_source_authority,logo_verified_at')
            ->withCount('wards')
            ->when($workspaceFilters->countyId, fn (Builder $query) => $query->where('county_id', $workspaceFilters->countyId))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))
            ->orderBy(County::query()->select('code')->whereColumn('counties.id', 'sub_counties.county_id'))
            ->orderBy('name')
            ->paginate(15, pageName: 'sub_counties_page')
            ->withQueryString();
        $wards = Ward::query()
            ->with('subCounty.county:id,name,code,logo_path,logo_source_authority,logo_verified_at')
            ->when($workspaceFilters->countyId, fn (Builder $query) => $query->whereHas('subCounty', fn (Builder $subCounty) => $subCounty->where('county_id', $workspaceFilters->countyId)))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhereHas('subCounty', fn (Builder $subCounty) => $subCounty->where('name', 'ilike', "%{$search}%"))))
            ->orderBy(SubCounty::query()->join('counties', 'counties.id', '=', 'sub_counties.county_id')->select('counties.code')->whereColumn('sub_counties.id', 'wards.sub_county_id'))
            ->orderBy(SubCounty::query()->select('name')->whereColumn('sub_counties.id', 'wards.sub_county_id'))
            ->orderBy('code')
            ->paginate(15, pageName: 'wards_page')
            ->withQueryString();

        return Inertia::render('reference-data/index', [
            'filters' => [
                'search' => $search,
                'from' => $workspaceFilters->from,
                'to' => $workspaceFilters->to,
                'county_id' => $workspaceFilters->countyId,
                'sector_id' => $workspaceFilters->sectorId,
                'status' => $workspaceFilters->status,
            ],
            'counties' => $counties->through(fn (County $county): array => [
                'id' => $county->id,
                'identity' => $county->identityCell(),
                'region' => $county->region,
                'mapX' => $county->map_x,
                'mapY' => $county->map_y,
                'references' => $county->users_count + $county->assessments_count + $county->documents_count + $county->grants_count + $county->programme_coverages_count,
            ]),
            'organizations' => $organizations->through(fn (Organization $organization): array => ['id' => $organization->id, 'code' => $organization->code, 'name' => $organization->name, 'type' => $organization->type, 'county' => $organization->county?->identityCell(), 'email' => $organization->email, 'status' => $organization->status]),
            'sectors' => $sectors->through(fn (Sector $sector): array => ['id' => $sector->id, 'code' => $sector->code, 'name' => $sector->name, 'parent' => $sector->parent ? ['id' => $sector->parent->id, 'code' => $sector->parent->code, 'name' => $sector->parent->name] : null, 'description' => $sector->description, 'isActive' => $sector->is_active]),
            'programmes' => $programmes->through(fn (Programme $programme): array => ['id' => $programme->id, 'code' => $programme->code, 'name' => $programme->name, 'description' => $programme->description, 'organization' => $programme->leadOrganization?->name, 'sector' => $programme->sector?->name, 'startsOn' => $programme->starts_on?->toDateString(), 'endsOn' => $programme->ends_on?->toDateString(), 'status' => $programme->status, 'budgetAmount' => $programme->budget_amount, 'currency' => $programme->currency]),
            'subCounties' => $subCounties->through(fn (SubCounty $subCounty): array => [
                'id' => $subCounty->id,
                'code' => $subCounty->code,
                'name' => $subCounty->name,
                'classification' => $subCounty->classification,
                'county' => $subCounty->county->identityCell(),
                'wardCount' => $subCounty->wards_count,
                'effectiveFrom' => $subCounty->effective_from->toDateString(),
                'sourceAuthority' => $subCounty->source_authority,
                'checksum' => $subCounty->source_checksum_sha256,
            ]),
            'wards' => $wards->through(fn (Ward $ward): array => [
                'id' => $ward->id,
                'code' => $ward->code,
                'name' => $ward->name,
                'subCounty' => ['id' => $ward->subCounty->id, 'code' => $ward->subCounty->code, 'name' => $ward->subCounty->name],
                'county' => $ward->subCounty->county->identityCell(),
                'registeredVoters2022' => $ward->registered_voters_2022,
                'effectiveFrom' => $ward->effective_from->toDateString(),
                'sourceAuthority' => $ward->source_authority,
                'checksum' => $ward->source_checksum_sha256,
            ]),
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

    public function storeCounty(StoreCountyRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $attributes = $request->validated();
        $county = County::create([...$attributes, 'slug' => Str::slug($attributes['name'])]);
        $this->auditCounty($request, $auditLogger, $county, 'reference.county.created');

        return $this->success(__('reference-data.governance.outcomes.county_created'));
    }

    public function updateCounty(UpdateCountyRequest $request, County $county, AuditLogger $auditLogger): RedirectResponse
    {
        $attributes = $request->validated();
        $county->update([...$attributes, 'slug' => Str::slug($attributes['name'])]);
        $this->auditCounty($request, $auditLogger, $county, 'reference.county.updated');

        return $this->success(__('reference-data.governance.outcomes.county_updated'));
    }

    public function destroyCounty(BulkArchiveCountiesRequest $request, County $county, BulkArchiveCounties $archive): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $archive->handle($user, [$county->id]);

        return $this->success(__('reference-data.governance.outcomes.county_archived'));
    }

    public function bulkArchiveCounties(BulkArchiveCountiesRequest $request, BulkArchiveCounties $archive): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $archive->handle($user, $request->ids());

        return $this->success(trans_choice('reference-data.governance.outcomes.counties_archived', $count));
    }

    public function storeRelease(StoreReferenceDataReleaseRequest $request, CreateReferenceDataRelease $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $create->handle($user, $request->string('change_summary')->toString());

        return $this->success(__('reference-data.governance.outcomes.release_submitted'));
    }

    public function publishRelease(PublishReferenceDataReleaseRequest $request, ReferenceDataRelease $release, PublishReferenceDataRelease $publish): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{approval_reference: string, effective_from: string} $attributes */
        $attributes = $request->validated();
        $publish->handle($release, $user, $attributes);

        return $this->success(__('reference-data.governance.outcomes.release_published'));
    }

    public function storeOrganization(StoreOrganizationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $organization = Organization::create($request->validated());
        $this->audit($request, $auditLogger, $organization, 'reference.organization.created');

        return $this->success(__('reference-data.governance.outcomes.organization_created'));
    }

    public function updateOrganization(UpdateOrganizationRequest $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $organization->update($request->validated());
        $this->audit($request, $auditLogger, $organization, 'reference.organization.updated');

        return $this->success(__('reference-data.governance.outcomes.organization_updated'));
    }

    public function destroyOrganization(Request $request, Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($organization->programmes()->exists(), 409, __('reference-data.governance.errors.organization_has_programmes'));
        $this->audit($request, $auditLogger, $organization, 'reference.organization.archived');
        $organization->delete();

        return $this->success(__('reference-data.governance.outcomes.organization_archived'));
    }

    public function storeSector(StoreSectorRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $sector = Sector::create($request->validated());
        $this->audit($request, $auditLogger, $sector, 'reference.sector.created');

        return $this->success(__('reference-data.governance.outcomes.sector_created'));
    }

    public function updateSector(UpdateSectorRequest $request, Sector $sector, AuditLogger $auditLogger): RedirectResponse
    {
        $sector->update($request->validated());
        $this->audit($request, $auditLogger, $sector, 'reference.sector.updated');

        return $this->success(__('reference-data.governance.outcomes.sector_updated'));
    }

    public function destroySector(Request $request, Sector $sector, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($sector->programmes()->exists(), 409, __('reference-data.governance.errors.sector_has_programmes'));
        abort_if($sector->children()->exists(), 409, __('reference-data.governance.errors.sector_has_children'));
        $this->audit($request, $auditLogger, $sector, 'reference.sector.archived');
        $sector->delete();

        return $this->success(__('reference-data.governance.outcomes.sector_archived'));
    }

    public function storeProgramme(StoreProgrammeRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $programme = Programme::create($request->validated());
        $this->audit($request, $auditLogger, $programme, 'reference.programme.created');

        return $this->success(__('reference-data.governance.outcomes.programme_created'));
    }

    public function updateProgramme(UpdateProgrammeRequest $request, Programme $programme, AuditLogger $auditLogger): RedirectResponse
    {
        $programme->update($request->validated());
        $this->audit($request, $auditLogger, $programme, 'reference.programme.updated');

        return $this->success(__('reference-data.governance.outcomes.programme_updated'));
    }

    public function destroyProgramme(Request $request, Programme $programme, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        abort_if($programme->countyCoverages()->exists(), 409, __('reference-data.governance.errors.programme_has_coverage'));
        $this->audit($request, $auditLogger, $programme, 'reference.programme.archived');
        $programme->delete();

        return $this->success(__('reference-data.governance.outcomes.programme_archived'));
    }

    public function storeProgrammeCountyCoverage(StoreProgrammeCountyCoverageRequest $request, CreateProgrammeCountyCoverage $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{programme_id: string, county_id: string, implementation_lead_id?: string|null, starts_on: string, ends_on?: string|null, status: string, funding_allocation?: int|float|string|null, currency: string, source_reference: string, notes?: string|null} $attributes */
        $attributes = $request->validated();
        $create->handle($user, $attributes);

        return $this->success(__('reference-data.coverage.outcomes.created'));
    }

    public function destroyProgrammeCountyCoverage(Request $request, ProgrammeCountyCoverage $programmeCountyCoverage, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageReferenceData->value);
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $programmeCountyCoverage, 'reference.programme-coverage.archived', __('reference-data.coverage.audit.archived'), $programmeCountyCoverage->county_id, [
            'programme_id' => $programmeCountyCoverage->programme_id,
            'county_id' => $programmeCountyCoverage->county_id,
            'source_reference' => $programmeCountyCoverage->source_reference,
        ]);
        $programmeCountyCoverage->delete();

        return $this->success(__('reference-data.coverage.outcomes.archived'));
    }

    private function audit(Request $request, AuditLogger $auditLogger, Organization|Sector|Programme $subject, string $action): void
    {
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $subject, $action, __('reference-data.governance.audit.reference_changed', ['name' => $subject->name]));
    }

    private function auditCounty(Request $request, AuditLogger $auditLogger, County $county, string $action): void
    {
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $county, $action, __('reference-data.governance.audit.county_changed', ['name' => $county->name]), $county->id, ['code' => $county->code]);
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
