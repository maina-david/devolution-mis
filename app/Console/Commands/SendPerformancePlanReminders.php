<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\PerformancePlan;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('departmental-performance:send-reminders')]
#[Description('Send idempotent performance-plan deadline reminders and escalations')]
class SendPerformancePlanReminders extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $sent = 0;
        PerformancePlan::query()->whereNotIn('status', ['draft', 'active', 'finalized'])->whereNull('reminder_sent_at')->whereNotNull('decision_due_at')->where('decision_due_at', '<=', now()->addDay())->with(['employee:id,name', 'supervisor:id,name'])->chunkById(100, function ($plans) use (&$sent, $auditLogger): void {
            foreach ($plans as $plan) {
                $overdue = $plan->decision_due_at->isPast();
                $recipients = collect([$plan->employee, $plan->supervisor]);
                if ($overdue) {
                    $recipients = $recipients->merge(User::permission(ProgrammePermission::ReviewPerformancePlans->value)->get());
                }
                $recipients->filter()->unique('id')->each(fn (User $user) => $user->notify(new ProgrammeAlert($overdue ? 'Performance review overdue' : 'Performance review due soon', "{$plan->employee->name}'s {$plan->status} action is due {$plan->decision_due_at->diffForHumans()}.", 'departmental-performance')));
                $plan->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? now() : null]);
                $auditLogger->record(null, $plan, $overdue ? 'performance.plan.escalated' : 'performance.plan.reminded', $overdue ? 'Overdue performance-plan review escalated.' : 'Upcoming performance-plan deadline reminder sent.');
                $sent++;
            }
        });
        $this->components->info("Sent {$sent} performance-plan reminder(s).");

        return self::SUCCESS;
    }
}
