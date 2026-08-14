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
    protected $signature = 'support-desk:monitor-slas {--limit= : Maximum number of alerts to process in this run}';

    protected $description = 'Send idempotent service-desk SLA reminders and escalations';

    public function handle(RecordSupportTicketActivity $recordActivity, AuditLogger $auditLogger, EffectiveServiceDeskPolicyResolver $policyResolver): int
    {
        $limit = $this->monitorLimit();
        if ($limit === null) {
            $this->components->error(__('support-desk.console.invalid_limit'));

            return self::INVALID;
        }

        $processed = 0;
        $supportManagers = User::permission(ProgrammePermission::ManageSupportTickets->value)
            ->with(['county', 'assignedCounties'])
            ->get();
        $candidateMultiplier = max(1, (int) config('service-desk.monitor_candidate_multiplier', 5));
        $maximumCandidates = max(1, (int) config('service-desk.monitor_max_candidates', 5000));
        $candidateLimit = min($maximumCandidates, $limit * $candidateMultiplier);
        $lookaheadHours = max(0.25, (float) config('service-desk.monitor_max_lookahead_hours', 168));
        $candidateIds = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('reminder_sent_at')
            ->where(function ($query) use ($lookaheadHours): void {
                $latestCandidateDueAt = now()->addHours($lookaheadHours);
                $query->where(function ($firstResponse) use ($latestCandidateDueAt): void {
                    $firstResponse->whereNull('first_responded_at')->where('first_response_due_at', '<=', $latestCandidateDueAt);
                })->orWhere(function ($resolution) use ($latestCandidateDueAt): void {
                    $resolution->whereNotNull('first_responded_at')->where('resolution_due_at', '<=', $latestCandidateDueAt);
                });
            })
            ->orderByRaw('CASE WHEN first_responded_at IS NULL THEN first_response_due_at ELSE resolution_due_at END')
            ->limit($candidateLimit)
            ->pluck('id');

        SupportTicket::query()
            ->whereIn('id', $candidateIds)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('reminder_sent_at')
            ->with(['assignee:id,name', 'requester:id,name', 'county', 'serviceDeskPolicy.businessCalendar', 'serviceDeskPolicy.rosterMembers.user'])
            ->chunkById(100, function ($tickets) use (&$processed, $limit, $recordActivity, $auditLogger, $supportManagers, $policyResolver): ?bool {
                foreach ($tickets as $ticket) {
                    if ($processed >= $limit) {
                        return false;
                    }

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
                    $titleKey = $overdue ? 'support-desk.ticket.notifications.sla_overdue_title' : 'support-desk.ticket.notifications.sla_approaching_title';
                    $recipients->each(fn (User $recipient) => $recipient->notify(ProgrammeAlert::translated($titleKey, 'support-desk.ticket.notifications.reference_subject', 'support-desk', messageParameters: ['reference' => $ticket->reference, 'subject' => $ticket->subject])));
                    $ticket->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? ($ticket->escalated_at ?? now()) : $ticket->escalated_at]);
                    $activity = $overdue ? 'sla_escalated' : 'sla_reminded';
                    $narrative = __($overdue ? 'support-desk.ticket.activity.sla_escalated' : 'support-desk.ticket.activity.sla_reminded');
                    $recordActivity->handle($ticket, null, $activity, $ticket->status, $ticket->status, $narrative, ['due_at' => $dueAt->toIso8601String(), 'recipients' => $recipients->count()]);
                    $auditLogger->record(null, $ticket, 'support.ticket.'.$activity, $narrative, $ticket->county_id, ['due_at' => $dueAt->toIso8601String(), 'recipients' => $recipients->count()]);
                    $processed++;
                }

                return $processed >= $limit ? false : null;
            });

        $this->components->info(__('support-desk.console.processed', ['count' => $processed]));

        return self::SUCCESS;
    }

    private function monitorLimit(): ?int
    {
        $configuredLimit = (int) config('service-desk.monitor_batch_limit', 500);
        $requestedLimit = $this->option('limit');
        $value = $requestedLimit === null ? $configuredLimit : filter_var($requestedLimit, FILTER_VALIDATE_INT);

        return is_int($value) && $value >= 1 && $value <= 5000 ? $value : null;
    }
}
