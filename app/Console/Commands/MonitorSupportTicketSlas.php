<?php

namespace App\Console\Commands;

use App\Actions\RecordSupportTicketActivity;
use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\EffectiveServiceDeskPolicyResolver;
use Illuminate\Console\Command;

class MonitorSupportTicketSlas extends Command
{
    protected $signature = 'support-desk:monitor-slas';

    protected $description = 'Send idempotent service-desk SLA reminders and escalations';

    public function handle(RecordSupportTicketActivity $recordActivity, AuditLogger $auditLogger, EffectiveServiceDeskPolicyResolver $policyResolver): int
    {
        $processed = 0;
        $supportManagers = User::permission(ProgrammePermission::ManageSupportTickets->value)
            ->with(['county', 'assignedCounties'])
            ->get();

        SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('reminder_sent_at')
            ->with(['assignee:id,name', 'requester:id,name', 'county', 'serviceDeskPolicy.businessCalendar', 'serviceDeskPolicy.rosterMembers.user'])
            ->chunkById(100, function ($tickets) use (&$processed, $recordActivity, $auditLogger, $supportManagers, $policyResolver): void {
                foreach ($tickets as $ticket) {
                    $dueAt = $ticket->first_responded_at === null ? $ticket->first_response_due_at : $ticket->resolution_due_at;
                    $policy = $ticket->serviceDeskPolicy;
                    $reminderHours = (float) config('service-desk.reminder_hours', 2);
                    if ($policy !== null && $ticket->service_desk_policy_checksum !== null) {
                        $policyResolver->verifyPinned($policy, $ticket->service_desk_policy_checksum);
                        $reminderHours = (float) $policyResolver->target($policy, $ticket->priority)['reminder'];
                    }
                    if (now()->lessThan($dueAt->copy()->subHours($reminderHours))) {
                        continue;
                    }
                    $overdue = $dueAt->isPast();
                    $rosterRecipients = collect();
                    if ($policy !== null) {
                        $stage = $ticket->first_responded_at === null ? 'first_response' : 'resolution';
                        $tier = $overdue ? $policyResolver->escalationTier($policy, $ticket->priority, $stage) : 1;
                        $rosterRecipients = $policyResolver->recipients($policy, $ticket->county_id, now(), $tier);
                    }
                    $legacyManagers = $policy === null ? $supportManagers->filter(
                        fn (User $manager): bool => $ticket->county_id === null
                            ? $manager->programmeRole()->hasNationalScope()
                            : $manager->programmeRole()->hasNationalScope() || $manager->canAccessCounty($ticket->county),
                    ) : collect();
                    $recipients = collect([$ticket->assignee, $ticket->requester])
                        ->merge($rosterRecipients)
                        ->merge($legacyManagers)
                        ->filter()
                        ->unique('id');
                    $title = $overdue ? 'Support SLA overdue' : 'Support SLA approaching';
                    $recipients->each(fn (User $recipient) => $recipient->notify(new ProgrammeAlert($title, "{$ticket->reference}: {$ticket->subject}", 'support-desk')));
                    $ticket->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? ($ticket->escalated_at ?? now()) : $ticket->escalated_at]);
                    $activity = $overdue ? 'sla_escalated' : 'sla_reminded';
                    $narrative = $overdue ? 'The active service target is overdue and was escalated to authorized support management.' : 'The active service target is approaching and reminders were issued.';
                    $recordActivity->handle($ticket, null, $activity, $ticket->status, $ticket->status, $narrative, ['due_at' => $dueAt->toIso8601String(), 'recipients' => $recipients->count()]);
                    $auditLogger->record(null, $ticket, 'support.ticket.'.$activity, $narrative, $ticket->county_id, ['due_at' => $dueAt->toIso8601String(), 'recipients' => $recipients->count()]);
                    $processed++;
                }
            });

        $this->components->info("Processed {$processed} service-desk SLA alert(s).");

        return self::SUCCESS;
    }
}
