<?php

namespace App\Console\Commands;

use App\Actions\RecordSupportTicketActivity;
use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class MonitorSupportTicketSlas extends Command
{
    protected $signature = 'support-desk:monitor-slas';

    protected $description = 'Send idempotent service-desk SLA reminders and escalations';

    public function handle(RecordSupportTicketActivity $recordActivity, AuditLogger $auditLogger): int
    {
        $processed = 0;
        $window = now()->addHours((int) config('service-desk.reminder_hours', 2));
        $supportManagers = User::permission(ProgrammePermission::ManageSupportTickets->value)
            ->with(['county', 'assignedCounties'])
            ->get();

        SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('reminder_sent_at')
            ->where(function ($query) use ($window): void {
                $query->where(fn ($query) => $query->whereNull('first_responded_at')->where('first_response_due_at', '<=', $window))
                    ->orWhere(fn ($query) => $query->whereNotNull('first_responded_at')->where('resolution_due_at', '<=', $window));
            })
            ->with(['assignee:id,name', 'requester:id,name', 'county'])
            ->chunkById(100, function ($tickets) use (&$processed, $recordActivity, $auditLogger, $supportManagers): void {
                foreach ($tickets as $ticket) {
                    $dueAt = $ticket->first_responded_at === null ? $ticket->first_response_due_at : $ticket->resolution_due_at;
                    $overdue = $dueAt->isPast();
                    $recipients = collect([$ticket->assignee, $ticket->requester])
                        ->merge($supportManagers->filter(
                            fn (User $manager): bool => $ticket->county_id === null
                                ? $manager->programmeRole()->hasNationalScope()
                                : $manager->programmeRole()->hasNationalScope() || $manager->canAccessCounty($ticket->county),
                        ))
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
