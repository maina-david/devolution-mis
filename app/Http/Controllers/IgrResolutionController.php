<?php

namespace App\Http\Controllers;

use App\Actions\CreateIgrForumMeeting;
use App\Actions\CreateIgrGapCategory;
use App\Actions\CreateIgrResolution;
use App\Actions\CreateIgrResolutionDependency;
use App\Actions\CreateIgrResolutionGap;
use App\Actions\RecordIgrResolutionUpdate;
use App\Actions\TransitionIgrResolutionGap;
use App\Actions\TransitionWorkflow;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreIgrForumMeetingRequest;
use App\Http\Requests\StoreIgrForumRequest;
use App\Http\Requests\StoreIgrGapCategoryRequest;
use App\Http\Requests\StoreIgrResolutionDependencyRequest;
use App\Http\Requests\StoreIgrResolutionGapRequest;
use App\Http\Requests\StoreIgrResolutionRequest;
use App\Http\Requests\StoreIgrResolutionUpdateRequest;
use App\Http\Requests\TransitionIgrResolutionGapRequest;
use App\Http\Requests\TransitionIgrResolutionRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\DocumentLink;
use App\Models\IgrForum;
use App\Models\IgrForumMeeting;
use App\Models\IgrGapCategory;
use App\Models\IgrResolution;
use App\Models\IgrResolutionAssignment;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\IgrResolutionUpdate;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\IgrDependencyAnalytics;
use App\Services\IgrGapAnalytics;
use App\Services\IgrGapScope;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IgrResolutionController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData, ProgrammeCountyScope $countyScope, IgrGapAnalytics $gapAnalytics, IgrGapScope $gapScope, IgrDependencyAnalytics $dependencyAnalytics): Response
    {
        Gate::authorize(ProgrammePermission::ViewIgrResolutions->value);
        $user = $this->user($request);
        $visibleGapIds = $gapScope->visibleTo($user)->select('id');
        $resolutions = $this->visibleResolutions($user, $countyScope)
            ->with(['forum:id,code,name', 'referenceDataRelease:id,version,effective_from,checksum', 'meeting:id,igr_forum_id,reference,title,held_on,venue,chair_user_id,quorum_confirmed,minutes_reference', 'meeting.chair:id,name', 'dependencies.prerequisiteResolution:id,resolution_number,title,status', 'dependents.dependentResolution:id,resolution_number,title,status', 'gaps' => function (Relation $relation) use ($visibleGapIds): void {
                $relation->getQuery()
                    ->whereIn('igr_resolution_gaps.id', clone $visibleGapIds)
                    ->with(['category:id,code,name', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'owner:id,name', 'resolver:id,name', 'accepter:id,name']);
            }, 'assignments.user:id,name', 'assignments.organization:id,name', 'assignments.county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'updates' => fn ($query) => $query->latest('reported_at')->limit(5), 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status'])
            ->latest('resolved_on')->limit(50)->get();
        $visibleResolutionIds = $resolutions->modelKeys();
        $gapQuery = $this->filteredGaps($gapScope->visibleTo($user), $request);

        return Inertia::render('igr-resolutions/index', [
            'workspace' => $workspaceData->igrResolutions($user, WorkspaceFilters::fromRequest($request)),
            'gapWorkspace' => $workspaceData->igrResolutionGaps($user, WorkspaceFilters::fromRequest($request)),
            'filters' => WorkspaceFilters::fromRequest($request),
            'gapAnalytics' => $gapAnalytics->report($gapQuery),
            'dependencyAnalytics' => $dependencyAnalytics->report($resolutions),
            'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageIgrResolutions->value), 'update' => $user->can(ProgrammePermission::UpdateIgrResolutions->value), 'close' => $user->can(ProgrammePermission::CloseIgrResolutions->value)],
            'resolutions' => $resolutions->map(fn (IgrResolution $resolution): array => [
                'id' => $resolution->id, 'number' => $resolution->resolution_number, 'title' => $resolution->title, 'text' => $resolution->resolution_text,
                'forum' => $resolution->forum->name, 'resolvedOn' => $resolution->resolved_on->toDateString(), 'dueOn' => $resolution->due_on->toDateString(), 'priority' => $resolution->priority,
                'meeting' => $resolution->meeting ? ['id' => $resolution->meeting->id, 'reference' => $resolution->meeting->reference, 'title' => $resolution->meeting->title, 'heldOn' => $resolution->meeting->held_on->toDateString(), 'venue' => $resolution->meeting->venue, 'chair' => $resolution->meeting->chair?->name, 'quorumConfirmed' => $resolution->meeting->quorum_confirmed, 'minutesReference' => $resolution->meeting->minutes_reference] : null,
                'status' => $resolution->status, 'progress' => $resolution->progress_percentage, 'gap' => $gapScope->activeHeadline($resolution), 'closureEvidence' => $resolution->closure_evidence,
                'referenceRelease' => $resolution->referenceDataRelease ? "v{$resolution->referenceDataRelease->version} · {$resolution->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned',
                'referenceChecksum' => $resolution->referenceDataRelease?->checksum,
                'dependencies' => $resolution->dependencies->whereIn('prerequisite_resolution_id', $visibleResolutionIds)->map(fn (IgrResolutionDependency $dependency): array => ['id' => $dependency->id, 'type' => $dependency->dependency_type, 'rationale' => $dependency->rationale, 'resolutionId' => $dependency->prerequisiteResolution->id, 'number' => $dependency->prerequisiteResolution->resolution_number, 'title' => $dependency->prerequisiteResolution->title, 'status' => $dependency->prerequisiteResolution->status])->values()->all(),
                'dependents' => $resolution->dependents->whereIn('dependent_resolution_id', $visibleResolutionIds)->map(fn (IgrResolutionDependency $dependency): array => ['id' => $dependency->id, 'type' => $dependency->dependency_type, 'resolutionId' => $dependency->dependentResolution->id, 'number' => $dependency->dependentResolution->resolution_number, 'title' => $dependency->dependentResolution->title, 'status' => $dependency->dependentResolution->status])->values()->all(),
                'gaps' => $resolution->gaps->map(fn (IgrResolutionGap $gap): array => ['id' => $gap->id, 'category' => "{$gap->category->code} · {$gap->category->name}", 'title' => $gap->title, 'description' => $gap->description, 'impact' => $gap->impact, 'severity' => $gap->severity, 'status' => $gap->status, 'dueOn' => $gap->due_on->toDateString(), 'overdue' => $gap->status !== 'accepted' && $gap->due_on->isPast(), 'county' => $gap->county?->identityCell(), 'owner' => $gap->owner->name, 'mitigationPlan' => $gap->mitigation_plan, 'resolutionNote' => $gap->resolution_note, 'resolver' => $gap->resolver?->name, 'accepter' => $gap->accepter?->name])->values()->all(),
                'assignments' => $resolution->assignments->map(fn (IgrResolutionAssignment $assignment): array => ['userId' => $assignment->user_id, 'countyId' => $assignment->county_id, 'user' => $assignment->user_id ? $assignment->user?->name : null, 'organization' => $assignment->organization_id ? $assignment->organization?->name : null, 'county' => $assignment->county?->identityCell(), 'role' => $assignment->responsibility_role, 'lead' => $assignment->is_lead])->values()->all(),
                'updates' => $resolution->updates->map(fn (IgrResolutionUpdate $update): array => ['id' => $update->id, 'progress' => $update->progress_percentage, 'narrative' => $update->narrative, 'gap' => $update->implementation_gap, 'evidence' => $update->evidence_reference, 'reportedAt' => $update->reported_at->toIso8601String()])->values()->all(),
                'documents' => $resolution->documentLinks->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'purpose' => $link->purpose, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
            ]),
            'options' => ['forums' => IgrForum::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']), 'meetings' => IgrForumMeeting::query()->where('quorum_confirmed', true)->with('forum:id,name')->latest('held_on')->get()->map(fn (IgrForumMeeting $meeting): array => ['id' => $meeting->id, 'name' => "{$meeting->reference} · {$meeting->forum->name} · {$meeting->held_on->toDateString()}"]), 'resolutions' => $this->visibleResolutions($user, $countyScope)->whereNotIn('status', ['closure_review', 'closed'])->orderBy('resolution_number')->get(['id', 'resolution_number', 'title'])->map(fn (IgrResolution $resolution): array => ['id' => $resolution->id, 'name' => "{$resolution->resolution_number} · {$resolution->title}"]), 'gapCategories' => IgrGapCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_severity']), 'counties' => $countyScope->query($user)->orderBy('code')->get()->map->identityCell()->values(), 'organizations' => Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'users' => User::query()->orderBy('name')->get(['id', 'name', 'email'])],
        ]);
    }

    public function storeMeeting(StoreIgrForumMeetingRequest $request, CreateIgrForumMeeting $createMeeting): RedirectResponse
    {
        $createMeeting->handle($this->user($request), $request->validated());

        return back()->with('success', 'Formal IGR meeting recorded.');
    }

    public function storeGapCategory(StoreIgrGapCategoryRequest $request, CreateIgrGapCategory $createCategory): RedirectResponse
    {
        $createCategory->handle($this->user($request), $request->validated());

        return back()->with('success', 'IGR gap category created.');
    }

    public function storeForum(StoreIgrForumRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $forum = IgrForum::create([...$request->validated(), 'created_by' => $user->id]);
        $auditLogger->record($user, $forum, 'igr.forum.created', "IGR forum {$forum->code} created.");

        return back()->with('success', 'IGR forum created.');
    }

    public function storeResolution(StoreIgrResolutionRequest $request, CreateIgrResolution $createResolution): RedirectResponse
    {
        $resolution = $createResolution->handle($this->user($request), $request->validated());
        $resolution->assignments()->with('user')->get()->pluck('user')->filter()->each(fn (User $user) => $user->notifyNow(new ProgrammeAlert('New IGR resolution assignment', "You are responsible for {$resolution->resolution_number}: {$resolution->title}.", 'igr-resolutions')));

        return back()->with('success', 'Resolution registered and responsible parties notified.');
    }

    public function storeUpdate(StoreIgrResolutionUpdateRequest $request, IgrResolution $resolution, RecordIgrResolutionUpdate $recordUpdate, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $this->authorizeResolution($this->user($request), $resolution, $countyScope);
        $recordUpdate->handle($resolution, $this->user($request), $request->validated());

        return back()->with('success', 'Implementation update recorded.');
    }

    public function storeDependency(StoreIgrResolutionDependencyRequest $request, IgrResolution $resolution, CreateIgrResolutionDependency $createDependency, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $prerequisite = IgrResolution::query()->whereKey((string) $request->validated('prerequisite_resolution_id'))->firstOrFail();
        $this->authorizeResolution($user, $resolution, $countyScope);
        $this->authorizeResolution($user, $prerequisite, $countyScope);
        $createDependency->handle($resolution, $prerequisite, $user, $request->validated());

        return back()->with('success', 'Resolution dependency recorded.');
    }

    public function storeGap(StoreIgrResolutionGapRequest $request, IgrResolution $resolution, CreateIgrResolutionGap $createGap, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeResolution($user, $resolution, $countyScope);
        $createGap->handle($resolution, $user, $request->validated());

        return back()->with('success', 'Implementation gap recorded and assigned.');
    }

    public function transitionGap(TransitionIgrResolutionGapRequest $request, IgrResolution $resolution, IgrResolutionGap $gap, TransitionIgrResolutionGap $transitionGap, ProgrammeCountyScope $countyScope, IgrGapScope $gapScope): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeResolution($user, $resolution, $countyScope);
        abort_unless($gap->igr_resolution_id === $resolution->id, 404);
        abort_unless($gapScope->visibleTo($user)->whereKey($gap)->exists(), 403);
        $transitionGap->handle($gap, $user, $request->validated('transition'), $request->validated('rationale'));

        return back()->with('success', 'Implementation gap lifecycle updated.');
    }

    public function transition(TransitionIgrResolutionRequest $request, IgrResolution $resolution, TransitionWorkflow $transitionWorkflow, AuditLogger $auditLogger, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeResolution($user, $resolution, $countyScope);
        $instance = $resolution->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, 'Resolution workflow is unavailable.');
        $name = $request->validated('transition');
        if (in_array($name, ['approve_closure', 'reject_closure'], true)) {
            Gate::authorize(ProgrammePermission::CloseIgrResolutions->value);
        }
        if ($name === 'submit_closure') {
            abort_if($resolution->dependencies()->where('dependency_type', 'blocks')->whereHas('prerequisiteResolution', fn (Builder $query) => $query->where('status', '!=', 'closed'))->exists(), 409, 'All blocking prerequisite resolutions must be closed before closure review.');
            abort_if($resolution->gaps()->where('status', '!=', 'accepted')->exists(), 409, 'All implementation gaps require independent acceptance before closure review.');
        }
        $hasCleanClosureEvidence = $resolution->documentLinks()->where('purpose', 'igr-implementation-evidence')->whereHas('document', fn (Builder $query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
        $transitioned = $transitionWorkflow->handle($instance, $name, $user, ['progress_percentage' => $resolution->progress_percentage, 'closure_evidence_present' => $hasCleanClosureEvidence], $request->validated('comment'));
        $resolution->update(['status' => $transitioned->current_state, 'closed_by' => $name === 'approve_closure' ? $user->id : $resolution->closed_by, 'closed_at' => $name === 'approve_closure' ? now() : $resolution->closed_at]);
        $auditLogger->record($user, $resolution, 'igr.resolution.transitioned', "Resolution {$resolution->resolution_number} transitioned to {$transitioned->current_state}.");

        return back()->with('success', 'Resolution lifecycle updated.');
    }

    /** @return Builder<IgrResolution> */
    private function visibleResolutions(User $user, ProgrammeCountyScope $countyScope): Builder
    {
        return IgrResolution::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id)->orWhereIn('county_id', $countyScope->query($user)->select('id'))));
    }

    /**
     * @param  Builder<IgrResolutionGap>  $query
     * @return Builder<IgrResolutionGap>
     */
    private function filteredGaps(Builder $query, WorkspaceIndexRequest $request): Builder
    {
        $search = $request->string('search')->trim()->toString();

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('due_on', '>=', $request->date('from')?->toDateString()))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('due_on', '<=', $request->date('to')?->toDateString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')->toString()))
            ->when($request->filled('severity'), fn (Builder $query) => $query->where('severity', $request->string('severity')->toString()))
            ->when($request->filled('gap_category_id'), fn (Builder $query) => $query->where('igr_gap_category_id', $request->string('gap_category_id')->toString()))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('title', 'ilike', '%'.$search.'%')->orWhere('severity', 'ilike', '%'.$search.'%')->orWhereHas('category', fn (Builder $category) => $category->where('name', 'ilike', '%'.$search.'%'))));
    }

    private function authorizeResolution(User $user, IgrResolution $resolution, ProgrammeCountyScope $countyScope): void
    {
        abort_unless($this->visibleResolutions($user, $countyScope)->whereKey($resolution)->exists(), 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
