<?php

namespace App\Http\Controllers;

use App\Actions\AssignSupportTicket;
use App\Actions\CreateServiceDeskPolicy;
use App\Actions\CreateSupportTicket;
use App\Actions\PublishServiceDeskPolicy;
use App\Actions\TransitionSupportTicket;
use App\Enums\ProgrammePermission;
use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\PublishServiceDeskPolicyRequest;
use App\Http\Requests\StoreServiceDeskPolicyRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\TransitionSupportTicketRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\BusinessCalendar;
use App\Models\ServiceDeskPolicy;
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
        /** @var list<array{id: string}> $workspaceRows */
        $workspaceRows = $workspace['rows'];
        $rowIds = array_map(
            fn (array $row): string => $row['id'],
            $workspaceRows,
        );
        $details = $access->query($user)
            ->whereIn('id', $rowIds)
            ->with([
                'referenceDataRelease:id,version,effective_from,checksum',
                'serviceDeskPolicy.businessCalendar:id,code,version,name,timezone,checksum',
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
        $canGovernPolicy = $user->can(ProgrammePermission::ConfigureSupportDesk->value) || $user->can(ProgrammePermission::PublishSupportDeskPolicy->value);

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
            'effectiveServicePolicy' => ServiceDeskPolicy::query()->where('status', 'published')->where('effective_from', '<=', now())->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))->latest('version')->first(['id', 'code', 'version', 'authority_status', 'checksum']),
            'servicePolicies' => $canGovernPolicy ? ServiceDeskPolicy::query()
                ->with(['businessCalendar:id,code,version,name,timezone,checksum', 'creator:id,name', 'publisher:id,name', 'rosterMembers.user:id,name', 'rosterMembers.county'])
                ->latest('version')
                ->orderBy('code')
                ->get()
                ->map(fn (ServiceDeskPolicy $policy): array => $this->policyDetail($policy))
                ->values() : [],
            'policyOptions' => [
                'calendars' => $user->can(ProgrammePermission::ConfigureSupportDesk->value)
                    ? BusinessCalendar::query()->where('status', 'published')->orderBy('name')->get()->map(fn (BusinessCalendar $calendar): array => ['id' => $calendar->id, 'name' => "{$calendar->name} · v{$calendar->version} · ".($calendar->effective_to?->toDateString() ?? __('support-desk.open_ended'))])->values()
                    : [],
                'resolvers' => $user->can(ProgrammePermission::ConfigureSupportDesk->value)
                    ? User::permission(ProgrammePermission::ResolveSupportTickets->value)->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name'])->map(fn (User $resolver): array => ['id' => $resolver->id, 'name' => $resolver->name])->values()
                    : [],
            ],
            'capabilities' => [
                'submit' => $user->can(ProgrammePermission::SubmitSupportTickets->value),
                'manage' => $user->can(ProgrammePermission::ManageSupportTickets->value),
                'resolve' => $user->can(ProgrammePermission::ResolveSupportTickets->value),
                'national' => $user->programmeRole()->hasNationalScope(),
                'userId' => $user->id,
                'configurePolicy' => $user->can(ProgrammePermission::ConfigureSupportDesk->value),
                'publishPolicy' => $user->can(ProgrammePermission::PublishSupportDeskPolicy->value),
            ],
        ]);
    }

    public function store(StoreSupportTicketRequest $request, CreateSupportTicket $action): RedirectResponse
    {
        $ticket = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('support-desk.ticket.flash.submitted', ['reference' => $ticket->reference]));
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $supportTicket, AssignSupportTicket $action): RedirectResponse
    {
        $assignee = User::query()->findOrFail($request->string('assigned_to')->toString());
        $action->handle($supportTicket, $this->user($request), $assignee, $request->string('narrative')->toString());

        return back()->with('success', __('support-desk.ticket.flash.assigned'));
    }

    public function transition(TransitionSupportTicketRequest $request, SupportTicket $supportTicket, TransitionSupportTicket $action): RedirectResponse
    {
        $action->handle($supportTicket, $this->user($request), $request->validated());

        return back()->with('success', __('support-desk.ticket.flash.transitioned'));
    }

    public function storePolicy(StoreServiceDeskPolicyRequest $request, CreateServiceDeskPolicy $action): RedirectResponse
    {
        $policy = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('support-desk.policy.flash.created', ['code' => $policy->code, 'version' => $policy->version]));
    }

    public function publishPolicy(PublishServiceDeskPolicyRequest $request, ServiceDeskPolicy $serviceDeskPolicy, PublishServiceDeskPolicy $action): RedirectResponse
    {
        $validated = $request->validated();
        $action->handle($serviceDeskPolicy, $this->user($request), [
            'authority_status' => (string) $validated['authority_status'],
            'approval_reference' => is_string($validated['approval_reference'] ?? null) ? $validated['approval_reference'] : null,
        ]);

        return back()->with('success', __('support-desk.policy.flash.published'));
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
            'servicePolicy' => $ticket->serviceDeskPolicy === null ? null : [
                'id' => $ticket->serviceDeskPolicy->id,
                'code' => $ticket->serviceDeskPolicy->code,
                'version' => $ticket->serviceDeskPolicy->version,
                'authorityStatus' => $ticket->serviceDeskPolicy->authority_status,
                'approvalReference' => $ticket->serviceDeskPolicy->approval_reference,
                'checksum' => $ticket->service_desk_policy_checksum,
                'calendar' => ['code' => $ticket->serviceDeskPolicy->businessCalendar->code, 'version' => $ticket->serviceDeskPolicy->businessCalendar->version, 'timezone' => $ticket->serviceDeskPolicy->businessCalendar->timezone, 'checksum' => $ticket->serviceDeskPolicy->businessCalendar->checksum],
            ],
            'activities' => $ticket->activities->map(fn ($activity): array => ['id' => $activity->id, 'actor' => $activity->actor_name, 'type' => $activity->activity_type, 'fromStatus' => $activity->from_status, 'toStatus' => $activity->to_status, 'narrative' => $activity->narrative, 'occurredAt' => $activity->occurred_at->toIso8601String(), 'checksum' => $activity->evidence_checksum])->values(),
            'documents' => $ticket->documentLinks->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'sizeBytes' => $link->document->size_bytes, 'checksum' => $link->document->content_checksum, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'recordStatus' => $link->document->record_status])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function policyDetail(ServiceDeskPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'code' => $policy->code,
            'version' => $policy->version,
            'name' => $policy->name,
            'description' => $policy->description,
            'status' => $policy->status,
            'authorityStatus' => $policy->authority_status,
            'approvalReference' => $policy->approval_reference,
            'effectiveFrom' => $policy->effective_from->toIso8601String(),
            'effectiveTo' => $policy->effective_to?->toIso8601String(),
            'calendar' => ['id' => $policy->businessCalendar->id, 'name' => $policy->businessCalendar->name, 'code' => $policy->businessCalendar->code, 'version' => $policy->businessCalendar->version, 'timezone' => $policy->businessCalendar->timezone, 'checksum' => $policy->businessCalendar->checksum],
            'categories' => $policy->categories,
            'channels' => $policy->channels,
            'priorityTargets' => $policy->priority_targets,
            'escalationRules' => $policy->escalation_rules,
            'creator' => $policy->creator->name,
            'publisher' => $policy->publisher?->name,
            'publishedAt' => $policy->published_at?->toIso8601String(),
            'checksum' => $policy->checksum,
            'roster' => $policy->rosterMembers->map(fn ($member): array => ['id' => $member->id, 'user' => $member->user->name, 'county' => $member->county?->identityCell(), 'tier' => $member->tier, 'dutyRole' => $member->duty_role, 'isPrimary' => $member->is_primary, 'startsAt' => $member->starts_at->toIso8601String(), 'endsAt' => $member->ends_at?->toIso8601String()])->values(),
        ];
    }

    private function user(WorkspaceIndexRequest|StoreSupportTicketRequest|AssignSupportTicketRequest|TransitionSupportTicketRequest|StoreServiceDeskPolicyRequest|PublishServiceDeskPolicyRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
