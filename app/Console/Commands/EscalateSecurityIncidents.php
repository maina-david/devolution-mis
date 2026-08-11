<?php

namespace App\Console\Commands;

use App\Actions\RecordSecurityIncidentEvent;
use App\Enums\ProgrammePermission;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('security:escalate-incidents')]
#[Description('Send idempotent reminders and escalations against snapshotted incident response targets')]
class EscalateSecurityIncidents extends Command
{
    public function handle(RecordSecurityIncidentEvent $recordEvent, AuditLogger $auditLogger): int
    {
        $sent = 0;
        $window = now()->addMinutes((int) config('security-governance.incident_reminder_minutes', 15));
        SecurityIncident::query()
            ->whereIn('status', ['detected', 'acknowledged'])
            ->whereNull('reminder_sent_at')
            ->where(function ($query) use ($window): void {
                $query->where(fn ($query) => $query->where('status', 'detected')->where('acknowledgement_due_at', '<=', $window))
                    ->orWhere(fn ($query) => $query->where('status', 'acknowledged')->where('containment_due_at', '<=', $window));
            })
            ->with('incidentLead:id,name')
            ->chunkById(100, function ($incidents) use (&$sent, $recordEvent, $auditLogger): void {
                foreach ($incidents as $incident) {
                    $dueAt = $incident->status === 'detected' ? $incident->acknowledgement_due_at : $incident->containment_due_at;
                    $overdue = $dueAt->isPast();
                    $managers = User::permission(ProgrammePermission::ManageSecurityGovernance->value)->get();
                    collect([$incident->incidentLead])->merge($managers)->unique('id')->each(fn (User $user) => $user->notify(new ProgrammeAlert($overdue ? 'Security incident response overdue' : 'Security incident response target approaching', "{$incident->reference}: {$incident->title}", 'security-governance')));
                    $incident->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? now() : $incident->escalated_at]);
                    $transition = $overdue ? 'sla_escalated' : 'sla_reminded';
                    $narrative = $overdue ? 'The current response target passed without the required state transition; escalation notifications were issued.' : 'The current response target is approaching; reminder notifications were issued.';
                    $recordEvent->handle($incident, null, $transition, $incident->status, $incident->status, $narrative, $dueAt->toIso8601String());
                    $auditLogger->record(null, $incident, 'security.incident.'.$transition, $narrative, metadata: ['due_at' => $dueAt->toIso8601String(), 'record_type' => $incident->record_type]);
                    $sent++;
                }
            });
        $this->components->info("Sent {$sent} security incident response alert(s).");

        return self::SUCCESS;
    }
}
