<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\BusinessTimeCalculator;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\EffectiveServiceDeskPolicyResolver;
use App\Services\SupportTicketAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSupportTicket
{
    public function __construct(
        private SupportTicketAccess $access,
        private EffectiveReferenceDataReleaseResolver $referenceData,
        private EffectiveServiceDeskPolicyResolver $servicePolicy,
        private BusinessTimeCalculator $businessTime,
        private RecordSupportTicketActivity $recordActivity,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $requester, array $attributes): SupportTicket
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        $this->access->assertCounty($requester, $countyId);
        $priority = (string) $attributes['priority'];
        $channel = (string) $attributes['channel'];
        abort_if($channel !== 'web' && ! $requester->can(ProgrammePermission::ManageSupportTickets->value), 403, 'Assisted-channel tickets may be logged only by support managers.');

        $ticket = DB::transaction(function () use ($requester, $attributes, $countyId, $priority, $channel): SupportTicket {
            $requestedAt = now()->startOfSecond();
            $release = $this->referenceData->forSupportTicket($countyId, $requestedAt);
            $policy = $this->servicePolicy->resolve($requestedAt);
            $target = $this->servicePolicy->target($policy, $priority);
            $categoryCodes = collect($policy->categories)->pluck('code');
            abort_unless($categoryCodes->contains((string) $attributes['category']), 422, 'The selected category is not available in the effective service catalogue.');
            abort_unless(in_array($channel, $policy->channels, true), 422, 'The selected support channel is not enabled by the effective service policy.');
            abort_if($this->servicePolicy->recipients($policy, $countyId, $requestedAt, 1)->isEmpty(), 503, 'No active tier-one support responder covers this request.');
            $ticket = SupportTicket::create([
                'reference_data_release_id' => $release->id,
                'service_desk_policy_id' => $policy->id,
                'service_desk_policy_checksum' => $policy->checksum,
                'requester_id' => $requester->id,
                'county_id' => $countyId,
                'reference' => 'SUP-'.$requestedAt->format('Ym').'-'.Str::upper(Str::random(8)),
                'category' => $attributes['category'],
                'priority' => $priority,
                'channel' => $channel,
                'subject' => $attributes['subject'],
                'description' => $attributes['description'],
                'status' => 'open',
                'requested_at' => $requestedAt,
                'first_response_due_at' => $this->businessTime->addHours($policy->businessCalendar, $requestedAt, (float) $target['first_response']),
                'resolution_due_at' => $this->businessTime->addHours($policy->businessCalendar, $requestedAt, (float) $target['resolution']),
                'last_activity_at' => $requestedAt,
            ]);
            $this->recordActivity->handle($ticket, $requester, 'created', 'none', 'open', 'Support request submitted to the governed service desk.', ['channel' => $channel, 'priority' => $priority, 'reference_data_release_id' => $release->id, 'reference_data_checksum' => $release->checksum, 'service_desk_policy_id' => $policy->id, 'service_desk_policy_checksum' => $policy->checksum, 'business_calendar_id' => $policy->business_calendar_id, 'business_calendar_checksum' => $policy->businessCalendar->checksum]);
            $this->auditLogger->record($requester, $ticket, 'support.ticket.created', "Service-desk ticket {$ticket->reference} submitted.", $countyId, ['priority' => $priority, 'category' => $ticket->category, 'channel' => $channel, 'reference_data_release_id' => $release->id, 'reference_data_checksum' => $release->checksum, 'service_desk_policy_id' => $policy->id, 'service_desk_policy_checksum' => $policy->checksum, 'business_calendar_id' => $policy->business_calendar_id]);

            return $ticket;
        });

        $ticket->load(['county', 'serviceDeskPolicy.rosterMembers.user']);
        $this->servicePolicy->recipients($ticket->serviceDeskPolicy, $countyId, $ticket->requested_at, 1)
            ->each(fn (User $responder) => $responder->notify(new ProgrammeAlert('New service-desk ticket', "{$ticket->reference}: {$ticket->subject}", 'support-desk')));

        return $ticket->refresh();
    }
}
