<?php

namespace App\Http\Controllers;

use App\Actions\AssignSupportTicket;
use App\Actions\CreateSupportTicket;
use App\Actions\TransitionSupportTicket;
use App\Enums\ProgrammePermission;
use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\TransitionSupportTicketRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Services\SupportTicketAccess;
use App\Support\WorkspaceFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupportDeskController extends Controller
{
    public function index(
        WorkspaceIndexRequest $request,
        SupportTicketAccess $access,
        ProgrammeWorkspaceData $workspaceData,
        ProgrammeCountyScope $countyScope,
        EffectiveReferenceDataReleaseResolver $referenceData,
    ): Response {
        Gate::authorize(ProgrammePermission::ViewSupportDesk->value);
        $user = $this->user($request);
        $filters = WorkspaceFilters::fromRequest($request);
        if ($filters->countyId !== null) {
            $access->assertCounty($user, $filters->countyId);
        }
        $workspace = $workspaceData->supportTickets($user, $filters);
        $rowIds = collect($workspace['rows'])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id))
            ->values();
        $details = $access->query($user)
            ->whereIn('id', $rowIds)
            ->with([
                'referenceDataRelease:id,version,effective_from,checksum',
                'county',
                'requester:id,name,email',
                'assignee:id,name,email',
                'resolver:id,name',
                'closer:id,name',
                'activities.actor:id,name',
                'documentLinks.document:id,title,original_name,mime_type,size_bytes,content_checksum,scan_status,ocr_status,record_status',
            ])
            ->get()
            ->mapWithKeys(fn (SupportTicket $ticket): array => [$ticket->id => $this->detail($ticket)]);
        $release = $referenceData->availableForSelection(now());
        $governedCountyIds = collect($release?->snapshot['counties'] ?? [])->pluck('id')->filter()->values();
        $counties = $countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get()->map->identityCell()->values();
        $summary = (array) $access->query($user)
            ->toBase()
            ->selectRaw("count(*) as total, count(*) filter (where status not in ('resolved', 'closed')) as active, count(*) filter (where assigned_to is null and status = 'open') as unassigned, count(*) filter (where status not in ('resolved', 'closed') and resolution_due_at < now()) as overdue")
            ->first();

        return Inertia::render('support-desk/index', [
            'workspace' => $workspace,
            'details' => $details,
            'filters' => $request->safe()->only(['from', 'to', 'search', 'county_id', 'status', 'per_page']),
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'active' => (int) ($summary['active'] ?? 0),
                'unassigned' => (int) ($summary['unassigned'] ?? 0),
                'overdue' => (int) ($summary['overdue'] ?? 0),
            ],
            'options' => [
                'counties' => $counties,
                'assignees' => $user->can(ProgrammePermission::ManageSupportTickets->value)
                    ? User::permission(ProgrammePermission::ResolveSupportTickets->value)->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name'])->map(fn (User $agent): array => ['id' => $agent->id, 'name' => $agent->name])->values()
                    : [],
            ],
            'catalogue' => $release === null ? ['available' => false] : ['available' => true, 'version' => $release->version, 'checksum' => $release->checksum],
            'capabilities' => [
                'submit' => $user->can(ProgrammePermission::SubmitSupportTickets->value),
                'manage' => $user->can(ProgrammePermission::ManageSupportTickets->value),
                'resolve' => $user->can(ProgrammePermission::ResolveSupportTickets->value),
                'national' => $user->programmeRole()->hasNationalScope(),
                'userId' => $user->id,
            ],
        ]);
    }

    public function store(StoreSupportTicketRequest $request, CreateSupportTicket $action): RedirectResponse
    {
        $ticket = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Support ticket {$ticket->reference} submitted.");
    }

    public function assign(AssignSupportTicketRequest $request, string $currentTeam, SupportTicket $supportTicket, AssignSupportTicket $action): RedirectResponse
    {
        $assignee = User::query()->findOrFail($request->string('assigned_to')->toString());
        $action->handle($supportTicket, $this->user($request), $assignee, $request->string('narrative')->toString());

        return back()->with('success', 'Support ticket assignment recorded.');
    }

    public function transition(TransitionSupportTicketRequest $request, string $currentTeam, SupportTicket $supportTicket, TransitionSupportTicket $action): RedirectResponse
    {
        $action->handle($supportTicket, $this->user($request), $request->validated());

        return back()->with('success', 'Support ticket workflow updated.');
    }

    /** @return array<string, mixed> */
    private function detail(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'channel' => $ticket->channel,
            'status' => $ticket->status,
            'county' => $ticket->county?->identityCell(),
            'requester' => ['id' => $ticket->requester->id, 'name' => $ticket->requester->name, 'email' => $ticket->requester->email],
            'assignee' => $ticket->assignee === null ? null : ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name, 'email' => $ticket->assignee->email],
            'resolver' => $ticket->resolver?->name,
            'closer' => $ticket->closer?->name,
            'resolutionSummary' => $ticket->resolution_summary,
            'requestedAt' => $ticket->requested_at->toIso8601String(),
            'firstResponseDueAt' => $ticket->first_response_due_at->toIso8601String(),
            'firstRespondedAt' => $ticket->first_responded_at?->toIso8601String(),
            'resolutionDueAt' => $ticket->resolution_due_at->toIso8601String(),
            'resolvedAt' => $ticket->resolved_at?->toIso8601String(),
            'closedAt' => $ticket->closed_at?->toIso8601String(),
            'referenceData' => ['version' => $ticket->referenceDataRelease->version, 'effectiveFrom' => $ticket->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $ticket->referenceDataRelease->checksum],
            'activities' => $ticket->activities->map(fn ($activity): array => ['id' => $activity->id, 'actor' => $activity->actor_name, 'type' => $activity->activity_type, 'fromStatus' => $activity->from_status, 'toStatus' => $activity->to_status, 'narrative' => $activity->narrative, 'occurredAt' => $activity->occurred_at->toIso8601String(), 'checksum' => $activity->evidence_checksum])->values(),
            'documents' => $ticket->documentLinks->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'sizeBytes' => $link->document->size_bytes, 'checksum' => $link->document->content_checksum, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'recordStatus' => $link->document->record_status])->values(),
        ];
    }

    private function user(WorkspaceIndexRequest|StoreSupportTicketRequest|AssignSupportTicketRequest|TransitionSupportTicketRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
