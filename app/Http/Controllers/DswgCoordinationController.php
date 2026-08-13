<?php

namespace App\Http\Controllers;

use App\Actions\CreateDswgAction;
use App\Actions\CreateDswgCollaborationThread;
use App\Actions\CreateDswgMeetingSeries;
use App\Actions\CreateDswgWorkingGroup;
use App\Actions\StartWorkflow;
use App\Actions\TransitionWorkflow;
use App\Enums\ProgrammePermission;
use App\Http\Requests\ApproveDswgMinutesRequest;
use App\Http\Requests\RecordDswgMeetingOutcomesRequest;
use App\Http\Requests\RespondDswgInvitationRequest;
use App\Http\Requests\StoreDswgActionRequest;
use App\Http\Requests\StoreDswgCollaborationMessageRequest;
use App\Http\Requests\StoreDswgCollaborationThreadRequest;
use App\Http\Requests\StoreDswgDecisionRequest;
use App\Http\Requests\StoreDswgMeetingRequest;
use App\Http\Requests\StoreDswgMeetingSeriesRequest;
use App\Http\Requests\StoreDswgWorkingGroupRequest;
use App\Http\Requests\TransitionDswgActionRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\DswgAction;
use App\Models\DswgCollaborationMessage;
use App\Models\DswgCollaborationThread;
use App\Models\DswgDecision;
use App\Models\DswgMeeting;
use App\Models\DswgMeetingSeries;
use App\Models\DswgWorkingGroup;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowDefinition;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DswgCoordinationController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData, ProgrammeCountyScope $countyScope, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewDswg->value);
        $user = $this->user($request);
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($referenceDataRelease?->snapshot['counties'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all();
        $governedSectorIds = collect($referenceDataRelease?->snapshot['sectors'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all();
        $governedOrganizationIds = collect($referenceDataRelease?->snapshot['organizations'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all();
        $visibleGroupIds = $this->visibleGroups($user, $countyScope)->select('dswg_working_groups.id');
        $meetings = DswgMeeting::query()->whereIn('dswg_working_group_id', $visibleGroupIds)
            ->with([
                'workingGroup:id,code,name',
                'invitees:id,name',
                'decisions:id,dswg_meeting_id,code,title',
                'documentLinks' => fn ($query) => $query->whereHas('document', fn (Builder $document) => $document->whereNull('deleted_at'))->with(['document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status']),
            ])
            ->withCount(['decisions', 'actions'])
            ->latest('starts_at')->limit(50)->get();
        $series = DswgMeetingSeries::query()
            ->whereIn('dswg_working_group_id', $visibleGroupIds)
            ->with('workingGroup:id,name')
            ->withCount('meetings')
            ->latest()
            ->limit(25)
            ->get();
        $threads = DswgCollaborationThread::query()
            ->whereIn('dswg_working_group_id', $visibleGroupIds)
            ->with([
                'workingGroup:id,name',
                'creator:id,name',
                'messages' => fn ($query) => $query->with('author:id,name')->latest('posted_at')->limit(20),
            ])
            ->withCount('messages')
            ->latest('last_activity_at')
            ->limit(25)
            ->get();

        return Inertia::render('dswg/index', [
            'workspace' => $workspaceData->dswg($user, WorkspaceFilters::fromRequest($request)),
            'filters' => WorkspaceFilters::fromRequest($request),
            'capabilities' => [
                'manage' => $user->can(ProgrammePermission::ManageDswg->value),
                'participate' => $user->can(ProgrammePermission::ParticipateDswg->value),
                'manageActions' => $user->can(ProgrammePermission::ManageDswgActions->value),
                'verifyActions' => $user->can(ProgrammePermission::VerifyDswgActions->value),
            ],
            'meetings' => $meetings->map(fn (DswgMeeting $meeting): array => [
                'id' => $meeting->id, 'reference' => $meeting->reference, 'title' => $meeting->title, 'workingGroup' => $meeting->workingGroup->name,
                'startsAt' => $meeting->starts_at->toIso8601String(), 'endsAt' => $meeting->ends_at->toIso8601String(), 'mode' => $meeting->meeting_mode,
                'status' => $meeting->status, 'agenda' => $meeting->agenda, 'minutes' => $meeting->minutes, 'quorumRequired' => $meeting->quorum_required,
                'invitees' => $meeting->invitees->map(fn (User $invitee): array => ['id' => $invitee->id, 'name' => $invitee->name])->values(), 'decisions' => $meeting->decisions_count, 'actions' => $meeting->actions_count,
                'decisionOptions' => $meeting->decisions->map(fn (DswgDecision $decision): array => ['id' => $decision->id, 'code' => $decision->code, 'name' => $decision->title])->values(),
                'invitationStatus' => DB::table('dswg_meeting_user')->where('dswg_meeting_id', $meeting->id)->where('user_id', $user->id)->value('invitation_status'),
                'minutesRecordedBy' => $meeting->minutes_recorded_by,
                'documents' => $meeting->documentLinks->map(fn (DocumentLink $link): array => [
                    'id' => $link->document->id,
                    'purpose' => $link->purpose,
                    'title' => $link->document->title,
                    'category' => $link->document->category,
                    'sourceType' => $link->document->source_type,
                    'originalName' => $link->document->original_name,
                    'mimeType' => $link->document->mime_type,
                    'scanStatus' => $link->document->scan_status,
                    'ocrStatus' => $link->document->ocr_status,
                ])->values()->all(),
            ]),
            'series' => $series->map(fn (DswgMeetingSeries $meetingSeries): array => [
                'id' => $meetingSeries->id,
                'referencePrefix' => $meetingSeries->reference_prefix,
                'title' => $meetingSeries->title,
                'workingGroup' => $meetingSeries->workingGroup->name,
                'frequency' => $meetingSeries->frequency,
                'interval' => $meetingSeries->interval,
                'timezone' => $meetingSeries->timezone,
                'nextOccurrenceAt' => $meetingSeries->next_occurrence_at->toIso8601String(),
                'endsOn' => $meetingSeries->ends_on->toDateString(),
                'status' => $meetingSeries->status,
                'generatedMeetings' => $meetingSeries->meetings_count,
            ]),
            'threads' => $threads->map(fn (DswgCollaborationThread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'topic' => $thread->topic,
                'status' => $thread->status,
                'workingGroup' => $thread->workingGroup->name,
                'creator' => $thread->creator->name,
                'lastActivityAt' => $thread->last_activity_at->toIso8601String(),
                'messageCount' => $thread->messages_count,
                'messages' => $thread->messages->sortBy('posted_at')->values()->map(fn (DswgCollaborationMessage $message): array => [
                    'id' => $message->id,
                    'author' => $message->author->name,
                    'body' => $message->body,
                    'checksum' => $message->checksum,
                    'postedAt' => $message->posted_at->toIso8601String(),
                ])->all(),
            ]),
            'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()],
            'options' => [
                'workingGroups' => $this->visibleGroups($user, $countyScope)->orderBy('name')->get(['id', 'code', 'name']),
                'meetings' => $meetings->map(fn (DswgMeeting $meeting): array => ['id' => $meeting->id, 'name' => "{$meeting->reference} · {$meeting->title}"]),
                'counties' => $countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get(['id', 'name']),
                'sectors' => Sector::query()->whereIn('id', $governedSectorIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'organizations' => Organization::query()->whereIn('id', $governedOrganizationIds)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'users' => User::query()
                    ->when(! $user->can(ProgrammePermission::ManageDswg->value), fn (Builder $query) => $query->whereHas('dswgWorkingGroups', fn (Builder $groups) => $groups->whereIn('dswg_working_groups.id', $this->visibleGroups($user, $countyScope)->select('dswg_working_groups.id'))))
                    ->orderBy('name')->get(['id', 'name', 'email']),
            ],
        ]);
    }

    public function storeWorkingGroup(StoreDswgWorkingGroupRequest $request, CreateDswgWorkingGroup $createWorkingGroup): RedirectResponse
    {
        $user = $this->user($request);
        $createWorkingGroup->handle($user, $request->validated());

        return $this->success('Sector working group established.');
    }

    public function storeMeeting(StoreDswgMeetingRequest $request, StartWorkflow $startWorkflow, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $group = $this->visibleGroups($user, $countyScope)->whereKey($request->validated('dswg_working_group_id'))->firstOrFail();
        $inviteeIds = collect($request->array('invitee_ids'));
        abort_unless($group->members()->whereIn('users.id', $inviteeIds)->count() === $inviteeIds->count(), 422, 'Every invitee must be a working-group member.');
        abort_if((int) $request->validated('quorum_required') > $inviteeIds->count(), 422, 'Quorum cannot exceed the number of invitees.');

        $meeting = DB::transaction(function () use ($request, $user, $group, $inviteeIds, $startWorkflow): DswgMeeting {
            $meeting = $group->meetings()->create([...$request->safe()->except(['dswg_working_group_id', 'invitee_ids']), 'organized_by' => $user->id]);
            $definition = WorkflowDefinition::query()->where('code', 'DSWG-MEETING-LIFECYCLE')->firstOrFail();
            $instance = $startWorkflow->handle($definition, $meeting, $user, ['minutes_present' => false, 'quorum_met' => false]);
            $meeting->update(['workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
            $pivot = $inviteeIds->mapWithKeys(fn (string $id): array => [$id => ['invitation_status' => 'pending', 'attendance_status' => 'not_recorded', 'meeting_role' => 'participant', 'invited_at' => now()]])->all();
            $meeting->invitees()->sync($pivot);

            return $meeting->refresh();
        });
        $meeting->invitees()->each(fn (User $invitee) => $invitee->notifyNow(new ProgrammeAlert('DSWG meeting invitation', "You are invited to {$meeting->title} on {$meeting->starts_at->toDayDateTimeString()}.", 'dswg')));
        $auditLogger->record($user, $meeting, 'dswg.meeting.scheduled', "DSWG meeting {$meeting->reference} scheduled.", metadata: ['invitees' => $inviteeIds->all()]);

        return $this->success('Meeting scheduled and invitations sent.');
    }

    public function storeCollaborationThread(StoreDswgCollaborationThreadRequest $request, CreateDswgCollaborationThread $createThread, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $group = $this->visibleGroups($user, $countyScope)->whereKey($request->validated('dswg_working_group_id'))->firstOrFail();
        $isActiveMember = $group->members()->where('users.id', $user->id)->where('dswg_working_group_user.status', 'active')->exists();
        abort_unless($user->can(ProgrammePermission::ManageDswg->value) || $isActiveMember, 403);
        $thread = $createThread->handle($group->id, $user, [
            'title' => (string) $request->validated('title'),
            'topic' => (string) $request->validated('topic'),
        ]);
        $auditLogger->record($user, $thread, 'dswg.collaboration_thread.created', "DSWG collaboration thread {$thread->title} created.");

        return $this->success('Collaboration thread created.');
    }

    public function storeCollaborationMessage(StoreDswgCollaborationMessageRequest $request, DswgCollaborationThread $thread, CreateDswgCollaborationThread $checksum, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visibleGroups($user, $countyScope)->whereKey($thread->dswg_working_group_id)->exists(), 403);
        $isActiveMember = $thread->workingGroup->members()->where('users.id', $user->id)->where('dswg_working_group_user.status', 'active')->exists();
        abort_unless($user->can(ProgrammePermission::ManageDswg->value) || $isActiveMember, 403);
        abort_unless($thread->status === 'open', 409, __('dswg.thread_closed'));
        $postedAt = now();
        $message = DB::transaction(function () use ($thread, $user, $request, $postedAt, $checksum) {
            $message = $thread->messages()->create([
                'author_id' => $user->id,
                'body' => $request->validated('body'),
                'posted_at' => $postedAt,
                'checksum' => $checksum->checksum($thread->id, $user->id, $request->validated('body'), $postedAt->toIso8601String()),
            ]);
            $thread->update(['last_activity_at' => $postedAt]);

            return $message;
        });
        $auditLogger->record($user, $message, 'dswg.collaboration_message.posted', "Contribution posted to DSWG thread {$thread->title}.");

        return $this->success('Contribution posted.');
    }

    public function storeMeetingSeries(StoreDswgMeetingSeriesRequest $request, CreateDswgMeetingSeries $createSeries, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        $group = $this->visibleGroups($user, $countyScope)->whereKey($request->validated('dswg_working_group_id'))->firstOrFail();
        $series = $createSeries->handle($group, $user, $request->validated());

        return $this->success("Recurring meeting series {$series->reference_prefix} created and its rolling schedule generated.");
    }

    public function respondInvitation(RespondDswgInvitationRequest $request, DswgMeeting $meeting, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeMeeting($user, $meeting);
        abort_unless($meeting->invitees()->whereKey($user)->exists(), 403);
        $meeting->invitees()->updateExistingPivot($user->id, ['invitation_status' => $request->validated('invitation_status'), 'responded_at' => now()]);
        $auditLogger->record($user, $meeting, 'dswg.invitation.responded', "Meeting invitation marked {$request->validated('invitation_status')}.");

        return $this->success('Invitation response recorded.');
    }

    public function recordOutcomes(RecordDswgMeetingOutcomesRequest $request, DswgMeeting $meeting, TransitionWorkflow $transition, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeMeeting($user, $meeting);
        $instance = $meeting->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, 'Meeting workflow is unavailable.');
        $presentIds = collect($request->array('present_user_ids'));
        abort_unless($meeting->invitees()->whereIn('users.id', $presentIds)->count() === $presentIds->count(), 422, 'Attendance can include invited participants only.');
        $quorumMet = $presentIds->count() >= $meeting->quorum_required;
        $transitioned = $transition->handle($instance, 'record_outcomes', $user, ['minutes_present' => true, 'quorum_met' => $quorumMet], 'Meeting outcomes and draft minutes recorded.');
        foreach ($meeting->invitees()->pluck('users.id') as $inviteeId) {
            $meeting->invitees()->updateExistingPivot($inviteeId, ['attendance_status' => $presentIds->contains($inviteeId) ? 'present' : 'absent']);
        }
        $meeting->update(['minutes' => $request->validated('minutes'), 'minutes_recorded_by' => $user->id, 'minutes_recorded_at' => now(), 'status' => $transitioned->current_state]);
        $auditLogger->record($user, $meeting, 'dswg.meeting.outcomes_recorded', "Draft minutes recorded for {$meeting->reference}.", metadata: ['present' => $presentIds->count(), 'quorum_met' => $quorumMet]);

        return $this->success('Meeting outcomes recorded for independent minutes approval.');
    }

    public function approveMinutes(ApproveDswgMinutesRequest $request, DswgMeeting $meeting, TransitionWorkflow $transition, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeMeeting($user, $meeting);
        $instance = $meeting->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, 'Meeting workflow is unavailable.');
        abort_unless($meeting->documentLinks()->where('purpose', 'dswg-minutes-record')->whereHas('document', fn (Builder $query) => $query->whereNull('deleted_at')->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, 'A clean repository-linked minutes record is required before approval.');
        $transitioned = $transition->handle($instance, 'approve_minutes', $user, [], $request->validated('approval_comment'));
        $meeting->update(['minutes_approved_by' => $user->id, 'minutes_approved_at' => now(), 'status' => $transitioned->current_state]);
        $auditLogger->record($user, $meeting, 'dswg.minutes.approved', "Minutes approved for {$meeting->reference}.");

        return $this->success('Minutes approved and meeting closed.');
    }

    public function storeDecision(StoreDswgDecisionRequest $request, DswgMeeting $meeting, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeMeeting($this->user($request), $meeting);
        abort_unless(in_array($meeting->status, ['minutes_pending', 'closed'], true), 409, 'Decisions may be registered only after outcomes are recorded.');
        $user = $this->user($request);
        $decision = $meeting->decisions()->create([...$request->validated(), 'created_by' => $user->id, 'status' => 'adopted']);
        $auditLogger->record($user, $decision, 'dswg.decision.adopted', "DSWG decision {$decision->code} adopted.");

        return $this->success('Meeting decision registered.');
    }

    public function storeAction(StoreDswgActionRequest $request, DswgMeeting $meeting, CreateDswgAction $createAction): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeMeeting($user, $meeting);
        abort_unless(in_array($meeting->status, ['minutes_pending', 'closed'], true), 409, 'Actions may be assigned only after meeting outcomes are recorded.');
        $action = $createAction->handle($meeting, $user, $request->validated());
        $action->accountableUser->notifyNow(new ProgrammeAlert('New DSWG action assigned', "{$action->code}: {$action->title} is due {$action->due_on->toFormattedDateString()}.", 'dswg'));

        return $this->success('Accountable action created and assignee notified.');
    }

    public function transitionAction(TransitionDswgActionRequest $request, DswgAction $action, TransitionWorkflow $transition, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeMeeting($user, $action->meeting);
        $transitionName = $request->validated('transition');
        if (in_array($transitionName, ['verify', 'reject'], true)) {
            Gate::authorize(ProgrammePermission::VerifyDswgActions->value);
        } else {
            abort_unless($user->can(ProgrammePermission::ManageDswg->value) || $action->accountable_user_id === $user->id, 403);
        }
        $instance = $action->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, 'Action workflow is unavailable.');
        $hasCleanEvidence = $action->documentLinks()->where('purpose', 'dswg-action-evidence')->whereHas('document', fn (Builder $query) => $query->whereNull('deleted_at')->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
        $context = [
            'progress_percentage' => $request->validated('progress_percentage', $action->progress_percentage),
            'completion_evidence_present' => $hasCleanEvidence,
        ];
        $transitioned = $transition->handle($instance, $transitionName, $user, $context, $request->validated('comment'));
        $attributes = [
            'status' => $transitioned->current_state,
            'progress_percentage' => $context['progress_percentage'],
            'progress_note' => $request->validated('progress_note', $action->progress_note),
            'completion_evidence' => $request->validated('completion_evidence', $action->completion_evidence),
        ];
        if ($transitionName === 'submit_completion') {
            $attributes = [...$attributes, 'completed_by' => $user->id, 'completed_at' => now()];
        } elseif ($transitionName === 'verify') {
            $attributes = [...$attributes, 'verified_by' => $user->id, 'verified_at' => now()];
        } elseif ($transitionName === 'reject') {
            $attributes = [...$attributes, 'completed_by' => null, 'completed_at' => null];
        }
        $action->update($attributes);
        $auditLogger->record($user, $action, 'dswg.action.transitioned', "DSWG action {$action->code} transitioned via {$transitionName}.", $action->county_id, ['comment' => $request->validated('comment')]);

        return $this->success('Action workflow updated.');
    }

    /** @return Builder<DswgWorkingGroup> */
    private function visibleGroups(User $user, ProgrammeCountyScope $countyScope): Builder
    {
        return DswgWorkingGroup::query()->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyScope->query($user)->select('id')));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function authorizeMeeting(User $user, DswgMeeting $meeting): void
    {
        $meeting->loadMissing('workingGroup.counties');
        abort_unless($meeting->workingGroup->counties->contains(fn (County $county): bool => $user->canAccessCounty($county)), 403);
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
