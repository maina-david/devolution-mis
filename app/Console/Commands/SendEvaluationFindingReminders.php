<?php

namespace App\Console\Commands;

use App\Models\County;
use App\Models\EvaluationFinding;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('monitoring-evaluation:send-finding-reminders')]
#[Description('Send idempotent evaluation-recommendation deadline reminders and escalations')]
class SendEvaluationFindingReminders extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $processed = 0;
        $reminderCutoff = today()->addDays((int) config('monitoring-evaluation.finding_reminder_days_before_due'));
        $escalationPermission = (string) config('monitoring-evaluation.finding_escalation_permission');

        EvaluationFinding::query()
            ->where('status', '!=', 'closed')
            ->whereDate('due_at', '<=', $reminderCutoff)
            ->where(function (Builder $query): void {
                $query->whereNull('reminder_sent_at')
                    ->orWhere(function (Builder $query): void {
                        $query->whereDate('due_at', '<', today())->whereNull('escalated_at');
                    });
            })
            ->select('id')
            ->chunkById(100, function (Collection $findings) use (&$processed, $auditLogger, $reminderCutoff, $escalationPermission): void {
                foreach ($findings as $candidate) {
                    $alert = DB::transaction(function () use ($candidate, $reminderCutoff): ?array {
                        $finding = EvaluationFinding::query()
                            ->with(['owner:id,name', 'issuer:id,name', 'county:id,name'])
                            ->lockForUpdate()
                            ->find($candidate->id);

                        if (! $finding instanceof EvaluationFinding || $finding->status === 'closed' || $finding->due_at->isAfter($reminderCutoff)) {
                            return null;
                        }

                        $isOverdue = $finding->due_at->isBefore(today());
                        $isEscalation = $isOverdue && $finding->escalated_at === null;
                        $isReminder = $finding->reminder_sent_at === null;
                        if (! $isReminder && ! $isEscalation) {
                            return null;
                        }

                        $finding->update([
                            'reminder_sent_at' => $isReminder ? now() : $finding->reminder_sent_at,
                            'escalated_at' => $isEscalation ? now() : $finding->escalated_at,
                        ]);

                        return ['finding' => $finding, 'isEscalation' => $isEscalation];
                    });

                    if ($alert === null) {
                        continue;
                    }

                    /** @var EvaluationFinding $finding */
                    $finding = $alert['finding'];
                    $isEscalation = $alert['isEscalation'] === true;
                    $recipients = collect([$finding->owner]);
                    if ($isEscalation) {
                        $managers = User::permission($escalationPermission)->get()->filter(function (User $manager) use ($finding): bool {
                            if ($finding->county instanceof County) {
                                return $manager->canAccessCounty($finding->county);
                            }

                            return $manager->programmeRole()->hasNationalScope();
                        });
                        $recipients = $recipients->push($finding->issuer)->merge($managers);
                    }

                    $recipients->filter()->unique('id')->each(fn (User $recipient) => $recipient->notify(new ProgrammeAlert(
                        $isEscalation ? 'Evaluation recommendation overdue' : 'Evaluation recommendation due soon',
                        "{$finding->reference}: {$finding->title} is due {$finding->due_at->diffForHumans()}.",
                        'monitoring-evaluation',
                    )));
                    $auditLogger->record(
                        null,
                        $finding,
                        $isEscalation ? 'evaluation.finding.escalated' : 'evaluation.finding.reminded',
                        $isEscalation ? 'Overdue evaluation recommendation escalated.' : 'Upcoming evaluation recommendation deadline reminder sent.',
                        $finding->county_id,
                        ['due_at' => $finding->due_at->toDateString(), 'recipient_count' => $recipients->filter()->unique('id')->count()],
                    );
                    $processed++;
                }
            });

        $this->components->info("Processed {$processed} evaluation recommendation alert(s).");

        return self::SUCCESS;
    }
}
