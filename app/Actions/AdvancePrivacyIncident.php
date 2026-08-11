<?php

namespace App\Actions;

use App\Models\PrivacyIncident;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class AdvancePrivacyIncident
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PrivacyIncident $privacyIncident, User $actor, array $attributes): PrivacyIncident
    {
        return DB::transaction(function () use ($privacyIncident, $actor, $attributes): PrivacyIncident {
            $incident = PrivacyIncident::query()->lockForUpdate()->whereKey($privacyIncident->id)->sole();
            $transition = (string) $attributes['transition'];
            $allowed = ['contain' => ['reported'], 'assess' => ['contained'], 'record_notifications' => ['notification_required'], 'close' => ['remediation']];
            abort_unless(in_array($incident->status, $allowed[$transition], true), 409, 'This incident transition is not allowed from its current state.');

            $changes = match ($transition) {
                'contain' => ['containment_actions' => $attributes['containment_actions'], 'contained_at' => now(), 'status' => 'contained'],
                'assess' => $this->assessmentChanges($incident, $actor, $attributes),
                'record_notifications' => $this->notificationChanges($incident, $attributes),
                'close' => $this->closureChanges($incident, $actor, $attributes),
                default => abort(422, 'Unknown incident transition.'),
            };

            $incident->update($changes);
            $this->auditLogger->record($actor, $incident, 'privacy.incident.'.$transition, "Privacy incident {$incident->reference} advanced to {$incident->status}.", metadata: ['transition' => $transition, 'real_risk_of_harm' => $incident->real_risk_of_harm, 'notification_reference' => $incident->regulator_notification_reference]);

            return $incident->refresh();
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function assessmentChanges(PrivacyIncident $incident, User $actor, array $attributes): array
    {
        abort_if($incident->reported_by === $actor->id, 403, 'The incident reporter cannot independently assess risk of harm.');

        return ['assessed_by' => $actor->id, 'severity' => $attributes['severity'], 'real_risk_of_harm' => $attributes['real_risk_of_harm'], 'risk_assessment' => $attributes['risk_assessment'], 'assessed_at' => now(), 'status' => $attributes['real_risk_of_harm'] === 'yes' ? 'notification_required' : 'remediation'];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function notificationChanges(PrivacyIncident $incident, array $attributes): array
    {
        abort_unless($incident->documentLinks()->where('purpose', 'privacy-incident-notification-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, 'A clean private notification-evidence record is required before recording statutory notification.');
        $notifiedAt = now()->parse((string) $attributes['regulator_notified_at']);
        abort_if($notifiedAt->isAfter($incident->regulator_notification_due_at) && empty($attributes['regulator_delay_reason']), 422, 'A reason is required when the regulator notification was recorded after the statutory target.');

        return ['regulator_notified_at' => $notifiedAt, 'regulator_notification_reference' => $attributes['regulator_notification_reference'], 'regulator_delay_reason' => $attributes['regulator_delay_reason'] ?? null, 'subject_notification_decision' => $attributes['subject_notification_decision'], 'data_subjects_notified_at' => $attributes['data_subjects_notified_at'] ?? null, 'subject_notification_rationale' => $attributes['subject_notification_rationale'] ?? null, 'status' => 'remediation'];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function closureChanges(PrivacyIncident $incident, User $actor, array $attributes): array
    {
        abort_if(in_array($actor->id, [$incident->reported_by, $incident->assessed_by], true), 403, 'Incident closure requires an actor independent of reporting and risk assessment.');
        abort_unless($incident->documentLinks()->where('purpose', 'privacy-incident-closure-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, 'A clean private closure-evidence record is required before incident closure.');

        return ['closed_by' => $actor->id, 'root_cause' => $attributes['root_cause'], 'remediation_actions' => $attributes['remediation_actions'], 'closure_evidence_reference' => $attributes['closure_evidence_reference'], 'closed_at' => now(), 'status' => 'closed'];
    }
}
