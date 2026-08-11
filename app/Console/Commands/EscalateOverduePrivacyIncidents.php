<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\PrivacyIncident;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('privacy:escalate-breach-deadlines')]
#[Description('Send idempotent reminders and escalations for personal-data breach notification deadlines')]
class EscalateOverduePrivacyIncidents extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $sent = 0;
        PrivacyIncident::query()->where('status', 'notification_required')->where('real_risk_of_harm', 'yes')->whereNull('regulator_notified_at')->whereNull('reminder_sent_at')->where('regulator_notification_due_at', '<=', now()->addHours((int) config('privacy.breach_reminder_hours')))->with('incidentLead:id,name')->chunkById(100, function ($incidents) use (&$sent, $auditLogger): void {
            foreach ($incidents as $incident) {
                $overdue = $incident->regulator_notification_due_at->isPast();
                $managers = User::permission(ProgrammePermission::ManageDataGovernance->value)->get();
                collect([$incident->incidentLead])->merge($managers)->unique('id')->each(fn (User $user) => $user->notify(new ProgrammeAlert($overdue ? 'Privacy breach notification overdue' : 'Privacy breach notification deadline approaching', "{$incident->reference}: {$incident->title}", 'data-governance')));
                $incident->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? now() : null]);
                $auditLogger->record(null, $incident, $overdue ? 'privacy.incident.escalated' : 'privacy.incident.reminded', $overdue ? 'Overdue privacy breach notification escalated.' : 'Privacy breach notification deadline reminder sent.', $incident->county_id);
                $sent++;
            }
        });
        $this->components->info("Sent {$sent} privacy incident deadline alert(s).");

        return self::SUCCESS;
    }
}
