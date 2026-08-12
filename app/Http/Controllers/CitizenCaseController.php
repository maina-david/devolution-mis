<?php

namespace App\Http\Controllers;

use App\Actions\AddCitizenCaseMessage;
use App\Actions\TransitionWorkflow;
use App\Actions\TriageCitizenCase;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreCitizenCaseMessageRequest;
use App\Http\Requests\TransitionCitizenCaseRequest;
use App\Http\Requests\TriageCitizenCaseRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\CitizenCase;
use App\Models\CitizenCaseAttachment;
use App\Models\CitizenCaseMessage;
use App\Models\Organization;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CitizenCaseController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData, ProgrammeCountyScope $countyScope, EffectiveReferenceDataReleaseResolver $referenceDataResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewCitizenCases->value);
        $user = $this->user($request);
        $cases = $this->visibleCases($user, $countyScope)->with(['county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'sector:id,name', 'assignee:id,name', 'intakeReferenceDataRelease:id,version,effective_from,checksum', 'triageReferenceDataRelease:id,version,effective_from,checksum', 'messages' => fn ($query) => $query->latest('posted_at')->limit(10), 'attachments:id,citizen_case_id,title,original_name,mime_type,source_type,scan_status,ocr_status'])->latest()->limit(50)->get();
        $visibleCases = $this->visibleCases($user, $countyScope);
        $summary = [
            'total' => (clone $visibleCases)->count(),
            'open' => (clone $visibleCases)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'overdue' => (clone $visibleCases)->where('resolution_due_at', '<', now())->whereNotIn('status', ['resolved', 'closed'])->count(),
            'grievances' => (clone $visibleCases)->where('case_type', 'grievance')->count(),
            'satisfaction' => (clone $visibleCases)->whereNotNull('satisfaction_rating')->avg('satisfaction_rating'),
        ];

        $release = $referenceDataResolver->availableForCitizenIntake(now());
        $organizationIds = $this->snapshotIds($release?->snapshot['organizations'] ?? []);
        $sectorIds = $this->snapshotIds($release?->snapshot['sectors'] ?? []);

        return Inertia::render('citizen-cases/index', ['workspace' => $workspaceData->citizenCases($user, WorkspaceFilters::fromRequest($request)), 'filters' => WorkspaceFilters::fromRequest($request), 'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageCitizenCases->value), 'respond' => $user->can(ProgrammePermission::RespondCitizenCases->value), 'resolve' => $user->can(ProgrammePermission::ResolveCitizenCases->value)], 'summary' => $summary, 'cases' => $cases->map(fn (CitizenCase $case): array => $this->casePayload($case)), 'options' => ['users' => User::query()->whereHas('roles.permissions', fn ($query) => $query->where('name', ProgrammePermission::RespondCitizenCases->value))->orderBy('name')->get(['id', 'name', 'email']), 'organizations' => Organization::query()->whereIn('id', $organizationIds)->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'sectors' => Sector::query()->whereIn('id', $sectorIds)->where('is_active', true)->orderBy('name')->get(['id', 'name'])]]);
    }

    public function triage(TriageCitizenCaseRequest $request, CitizenCase $case, TriageCitizenCase $triageCase, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeCase($user, $case, $countyScope);
        $assignee = User::query()->whereKey($request->validated('assigned_to'))->firstOrFail();
        abort_unless($assignee->canAccessCounty($case->county), 422, 'The assignee is not authorized for this county.');
        $triageCase->handle($case, $user, $request->validated());
        $assignee->notify(new ProgrammeAlert('Citizen case assigned', "{$case->reference}: {$case->subject}", 'citizen-cases'));

        return back()->with('success', 'Case triaged and assigned.');
    }

    public function message(StoreCitizenCaseMessageRequest $request, CitizenCase $case, AddCitizenCaseMessage $addMessage, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeCase($user, $case, $countyScope);
        $this->authorizeHandler($user, $case);
        abort_if($request->validated('visibility') === 'internal' && ! $user->can(ProgrammePermission::ManageCitizenCases->value), 403);
        $addMessage->handle($case, $user, $request->validated('body'), $request->validated('visibility'), $request->file('attachment'), $request->validated('source_type', 'born_digital'));

        return back()->with('success', 'Case message recorded.');
    }

    public function transition(TransitionCitizenCaseRequest $request, CitizenCase $case, TransitionWorkflow $transitionWorkflow, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeCase($user, $case, $countyScope);
        $this->authorizeHandler($user, $case);
        $instance = $case->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, 'The case must be triaged first.');
        $name = $request->validated('transition');
        if (in_array($name, ['approve_resolution', 'reject_resolution'], true)) {
            Gate::authorize(ProgrammePermission::ResolveCitizenCases->value);
        }
        if ($name === 'close') {
            Gate::authorize(ProgrammePermission::ManageCitizenCases->value);
        }
        $resolutionSummary = $name === 'submit_resolution' ? $request->validated('resolution_summary') : $case->resolution_summary;
        $transitioned = $transitionWorkflow->handle($instance, $name, $user, ['resolution_summary_present' => filled($resolutionSummary)], $request->validated('comment'));
        $case->update(['status' => $transitioned->current_state, 'resolution_summary' => $resolutionSummary, 'escalated_at' => $name === 'escalate' ? now() : $case->escalated_at, 'resolved_at' => $name === 'approve_resolution' ? now() : $case->resolved_at]);
        $auditLogger->record($user, $case, 'citizen_case.transitioned', "Case {$case->reference} transitioned to {$transitioned->current_state}.", $case->county_id, ['transition' => $name]);

        return back()->with('success', 'Case workflow updated.');
    }

    public function attachment(Request $request, CitizenCaseAttachment $attachment, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): StreamedResponse
    {
        $user = $this->user($request);
        $case = $attachment->citizenCase;
        $this->authorizeCase($user, $case, $countyScope);
        abort_unless($attachment->scan_status === 'clean', 423, 'The attachment is quarantined.');
        abort_unless(Storage::exists($attachment->path), 404);
        $auditLogger->record($user, $attachment, 'citizen_case.attachment_downloaded', "Case attachment downloaded: {$attachment->title}.", $case->county_id);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    /** @return Builder<CitizenCase> */
    private function visibleCases(User $user, ProgrammeCountyScope $countyScope): Builder
    {
        return CitizenCase::query()->whereIn('county_id', $countyScope->query($user)->select('id'))->when(! $user->can(ProgrammePermission::ManageCitizenCases->value) && ! $user->can(ProgrammePermission::ResolveCitizenCases->value), fn (Builder $query) => $query->where('is_sensitive', false));
    }

    private function authorizeCase(User $user, CitizenCase $case, ProgrammeCountyScope $countyScope): void
    {
        abort_unless($this->visibleCases($user, $countyScope)->whereKey($case)->exists(), 403);
    }

    private function authorizeHandler(User $user, CitizenCase $case): void
    {
        abort_unless($user->can(ProgrammePermission::ManageCitizenCases->value) || $user->can(ProgrammePermission::ResolveCitizenCases->value) || $case->assigned_to === $user->id, 403);
    }

    /** @return array<string, mixed> */
    private function casePayload(CitizenCase $case): array
    {
        return ['id' => $case->id, 'reference' => $case->reference, 'type' => $case->case_type, 'category' => $case->category, 'channel' => $case->channel, 'county' => $case->county->identityCell(), 'sector' => $case->sector_id ? $case->sector?->name : null, 'subject' => $case->subject, 'description' => $case->description, 'citizenName' => $case->is_anonymous ? 'Anonymous' : $case->citizen_name, 'preferredContact' => $case->preferred_contact, 'accessibilityNeeds' => $case->accessibility_needs, 'priority' => $case->priority, 'status' => $case->status, 'sensitive' => $case->is_sensitive, 'assignee' => $case->assigned_to ? $case->assignee?->name : null, 'assignedTo' => $case->assigned_to, 'intakeReferenceData' => $this->releasePayload($case->intakeReferenceDataRelease), 'triageReferenceData' => $this->releasePayload($case->triageReferenceDataRelease), 'firstResponseDueAt' => $case->first_response_due_at->toIso8601String(), 'resolutionDueAt' => $case->resolution_due_at->toIso8601String(), 'resolutionSummary' => $case->resolution_summary, 'messages' => $case->messages->map(fn (CitizenCaseMessage $message): array => ['id' => $message->id, 'direction' => $message->direction, 'visibility' => $message->visibility, 'body' => $message->body, 'postedAt' => $message->posted_at->toIso8601String()])->values()->all(), 'attachments' => $case->attachments->map(fn (CitizenCaseAttachment $attachment): array => ['id' => $attachment->id, 'title' => $attachment->title, 'originalName' => $attachment->original_name, 'sourceType' => $attachment->source_type, 'scanStatus' => $attachment->scan_status, 'ocrStatus' => $attachment->ocr_status])->values()->all()];
    }

    /** @return array{version: int, effectiveFrom: string|null, checksum: string}|null */
    private function releasePayload(?ReferenceDataRelease $release): ?array
    {
        return $release ? ['version' => $release->version, 'effectiveFrom' => $release->effective_from?->toIso8601String(), 'checksum' => $release->checksum] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<string>
     */
    private function snapshotIds(array $records): array
    {
        return array_values(collect($records)->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
