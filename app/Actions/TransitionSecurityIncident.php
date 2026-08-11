<?php

namespace App\Actions;

use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionSecurityIncident
{
    public function __construct(private RecordSecurityIncidentEvent $recordEvent, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(SecurityIncident $securityIncident, User $actor, array $attributes): SecurityIncident
    {
        return DB::transaction(function () use ($securityIncident, $actor, $attributes): SecurityIncident {
            $incident = SecurityIncident::query()->lockForUpdate()->whereKey($securityIncident->id)->sole();
            $transition = (string) $attributes['transition'];
            $allowed = ['acknowledge' => ['detected', 'acknowledged'], 'contain' => ['acknowledged'], 'eradicate' => ['contained'], 'recover' => ['eradicated'], 'close' => ['recovered']];
            abort_unless(isset($allowed[$transition]) && in_array($incident->status, $allowed[$transition], true), 409, 'This incident transition is not allowed from its current state.');
            abort_if($transition === 'acknowledge' && $actor->id !== $incident->incident_lead_id, 403, 'Only the assigned incident lead may acknowledge responsibility.');
            if ($transition === 'close') {
                $this->guardClosure($incident, $actor);
            }

            $fromStatus = $incident->status;
            $changes = match ($transition) {
                'acknowledge' => ['status' => 'acknowledged', 'acknowledged_at' => now()],
                'contain' => ['status' => 'contained', 'contained_at' => now()],
                'eradicate' => ['status' => 'eradicated', 'eradicated_at' => now()],
                'recover' => ['status' => 'recovered', 'recovered_at' => now()],
                'close' => ['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now(), 'root_cause' => $attributes['root_cause'], 'corrective_actions' => $attributes['corrective_actions'], 'lessons_learned' => $attributes['lessons_learned'], 'exercise_outcome' => $incident->record_type === 'exercise' ? $attributes['exercise_outcome'] : 'not_assessed', 'next_exercise_due_at' => $incident->record_type === 'exercise' ? $attributes['next_exercise_due_at'] : null],
            };
            $incident->update([...$changes, 'last_transition_at' => now(), 'reminder_sent_at' => null]);
            $this->recordEvent->handle($incident, $actor, $transition, $fromStatus, $incident->status, (string) $attributes['narrative'], $attributes['evidence_reference'] ?? null);
            $this->auditLogger->record($actor, $incident, 'security.incident.'.$transition, "Security incident {$incident->reference} advanced from {$fromStatus} to {$incident->status}.", metadata: ['record_type' => $incident->record_type, 'playbook' => $incident->playbook, 'severity' => $incident->severity]);

            return $incident->refresh();
        });
    }

    private function guardClosure(SecurityIncident $incident, User $actor): void
    {
        abort_if(in_array($actor->id, [$incident->reported_by, $incident->incident_lead_id], true), 403, 'Closure requires an actor independent of reporting and incident leadership.');
        abort_if($incident->data_exposure === 'confirmed' && blank($incident->external_reference), 409, 'Confirmed data exposure requires a linked privacy/legal incident reference before closure.');
        abort_unless($incident->documentLinks()->where('purpose', 'security-incident-closure-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, 'A clean private closure-evidence record is required before incident closure.');
    }
}
