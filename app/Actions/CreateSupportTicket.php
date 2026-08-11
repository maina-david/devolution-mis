<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\SupportTicketAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSupportTicket
{
    public function __construct(
        private SupportTicketAccess $access,
        private EffectiveReferenceDataReleaseResolver $referenceData,
        private RecordSupportTicketActivity $recordActivity,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $requester, array $attributes): SupportTicket
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        $this->access->assertCounty($requester, $countyId);
        $priority = (string) $attributes['priority'];
        $sla = config("service-desk.sla_hours.{$priority}");
        abort_unless(is_array($sla) && is_numeric($sla['first_response'] ?? null) && is_numeric($sla['resolution'] ?? null), 500, 'The service-desk SLA policy is not configured.');
        $channel = (string) $attributes['channel'];
        abort_if($channel !== 'web' && ! $requester->can(ProgrammePermission::ManageSupportTickets->value), 403, 'Assisted-channel tickets may be logged only by support managers.');

        $ticket = DB::transaction(function () use ($requester, $attributes, $countyId, $priority, $sla, $channel): SupportTicket {
            $requestedAt = now()->startOfSecond();
            $release = $this->referenceData->forSupportTicket($countyId, $requestedAt);
            $ticket = SupportTicket::create([
                'reference_data_release_id' => $release->id,
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
                'first_response_due_at' => $requestedAt->copy()->addHours((int) $sla['first_response']),
                'resolution_due_at' => $requestedAt->copy()->addHours((int) $sla['resolution']),
                'last_activity_at' => $requestedAt,
            ]);
            $this->recordActivity->handle($ticket, $requester, 'created', 'none', 'open', 'Support request submitted to the governed service desk.', ['channel' => $channel, 'priority' => $priority, 'reference_data_release_id' => $release->id, 'reference_data_checksum' => $release->checksum]);
            $this->auditLogger->record($requester, $ticket, 'support.ticket.created', "Service-desk ticket {$ticket->reference} submitted.", $countyId, ['priority' => $priority, 'category' => $ticket->category, 'channel' => $channel, 'reference_data_release_id' => $release->id, 'reference_data_checksum' => $release->checksum]);

            return $ticket;
        });

        $ticket->load('county');
        User::permission(ProgrammePermission::ManageSupportTickets->value)
            ->get()
            ->filter(fn (User $manager): bool => $countyId === null ? $manager->programmeRole()->hasNationalScope() : $manager->programmeRole()->hasNationalScope() || $manager->canAccessCounty($ticket->county))
            ->each(fn (User $manager) => $manager->notify(new ProgrammeAlert('New service-desk ticket', "{$ticket->reference}: {$ticket->subject}", 'support-desk')));

        return $ticket->refresh();
    }
}
