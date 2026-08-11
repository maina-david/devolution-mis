<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\PartnerCollaborationAction;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('partners:send-action-reminders')]
#[Description('Send idempotent collaboration-action deadline reminders and overdue escalations')]
class SendPartnerCollaborationActionReminders extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $processed = 0;
        $cutoff = today()->addDays((int) config('partners.collaboration_action_reminder_days'));
        PartnerCollaborationAction::query()->where('status', '!=', 'completed')->whereDate('due_on', '<=', $cutoff)->where(function (Builder $query): void {
            $query->whereNull('reminder_sent_at')->orWhere(fn (Builder $query) => $query->whereDate('due_on', '<', today())->whereNull('escalated_at'));
        })->select('id')->chunkById(100, function (Collection $actions) use (&$processed, $auditLogger, $cutoff): void {
            foreach ($actions as $candidate) {
                $alert = DB::transaction(function () use ($candidate, $cutoff): ?array {
                    $action = PartnerCollaborationAction::query()->with(['accountableUser:id,name', 'county', 'plan.partner.organization:id,name'])->lockForUpdate()->find($candidate->id);
                    if (! $action instanceof PartnerCollaborationAction || $action->status === 'completed' || $action->due_on->isAfter($cutoff)) {
                        return null;
                    }
                    $overdue = $action->due_on->isBefore(today());
                    $escalation = $overdue && $action->escalated_at === null;
                    $reminder = $action->reminder_sent_at === null;
                    if (! $reminder && ! $escalation) {
                        return null;
                    }
                    $action->update(['reminder_sent_at' => $reminder ? now() : $action->reminder_sent_at, 'escalated_at' => $escalation ? now() : $action->escalated_at]);

                    return ['action' => $action, 'escalation' => $escalation];
                }, attempts: 3);
                if ($alert === null) {
                    continue;
                }
                /** @var PartnerCollaborationAction $action */
                $action = $alert['action'];
                $escalation = $alert['escalation'] === true;
                $recipients = User::query()->whereKey($action->accountable_user_id)->get();
                if ($escalation) {
                    $recipients = $recipients->merge(User::permission(ProgrammePermission::ManagePartners->value)->get()->filter(fn (User $manager): bool => $manager->canAccessCounty($action->county)));
                }
                $recipients->unique('id')->each(fn (User $recipient) => $recipient->notify(new ProgrammeAlert($escalation ? 'Partner collaboration action overdue' : 'Partner collaboration action due soon', "{$action->code}: {$action->title} is due {$action->due_on->toFormattedDateString()}.", 'partner-coordination')));
                $auditLogger->record(null, $action, $escalation ? 'partner.collaboration_action.escalated' : 'partner.collaboration_action.reminded', $escalation ? 'Overdue collaboration action escalated.' : 'Upcoming collaboration action reminder sent.', $action->county_id, ['due_on' => $action->due_on->toDateString(), 'recipient_count' => $recipients->unique('id')->count()]);
                $processed++;
            }
        });
        $this->components->info("Processed {$processed} collaboration action alert(s).");

        return self::SUCCESS;
    }
}
