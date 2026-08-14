<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ManageDataGovernance->value), 403, __('data-governance.privacy.errors.incident_advance_unauthorized'));

        return DB::transaction(function () use ($privacyIncident, $actor, $attributes): PrivacyIncident {
            $incident = PrivacyIncident::query()->lockForUpdate()->whereKey($privacyIncident->id)->sole();
            $transition = (string) $attributes['transition'];
            $allowed = ['contain' => ['reported'], 'assess' => ['contained'], 'record_notifications' => ['notification_required'], 'close' => ['remediation']];
            abort_unless(array_key_exists($transition, $allowed), 422, __('data-governance.privacy.errors.incident_unknown_transition'));
            abort_unless(in_array($incident->status, $allowed[$transition], true), 409, __('data-governance.privacy.errors.incident_invalid_state'));

            $changes = match ($transition) {
                'contain' => ['containment_actions' => $attributes['containment_actions'], 'contained_at' => now(), 'status' => 'contained'],
                'assess' => $this->assessmentChanges($incident, $actor, $attributes),
                'record_notifications' => $this->notificationChanges($incident, $attributes),
                'close' => $this->closureChanges($incident, $actor, $attributes),
            };

            $incident->update($changes);
            $this->auditLogger->record($actor, $incident, 'privacy.incident.'.$transition, __('data-governance.privacy.audit.incident_advanced', ['reference' => $incident->reference, 'status' => __('data-governance.privacy.statuses.'.$incident->status)]), metadata: ['transition' => $transition, 'real_risk_of_harm' => $incident->real_risk_of_harm, 'notification_reference' => $incident->regulator_notification_reference]);

            return $incident->refresh();
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function assessmentChanges(PrivacyIncident $incident, User $actor, array $attributes): array
    {
        abort_if($incident->reported_by === $actor->id, 403, __('data-governance.privacy.errors.incident_reporter_assessment'));

        return ['assessed_by' => $actor->id, 'severity' => $attributes['severity'], 'real_risk_of_harm' => $attributes['real_risk_of_harm'], 'risk_assessment' => $attributes['risk_assessment'], 'assessed_at' => now(), 'status' => $attributes['real_risk_of_harm'] === 'yes' ? 'notification_required' : 'remediation'];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function notificationChanges(PrivacyIncident $incident, array $attributes): array
    {
        abort_unless($incident->documentLinks()->where('purpose', 'privacy-incident-notification-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, __('data-governance.privacy.errors.incident_notification_evidence'));
        $notifiedAt = now()->parse((string) $attributes['regulator_notified_at']);
        abort_if($notifiedAt->isAfter($incident->regulator_notification_due_at) && empty($attributes['regulator_delay_reason']), 422, __('data-governance.privacy.errors.incident_notification_delay_reason'));

        return ['regulator_notified_at' => $notifiedAt, 'regulator_notification_reference' => $attributes['regulator_notification_reference'], 'regulator_delay_reason' => $attributes['regulator_delay_reason'] ?? null, 'subject_notification_decision' => $attributes['subject_notification_decision'], 'data_subjects_notified_at' => $attributes['data_subjects_notified_at'] ?? null, 'subject_notification_rationale' => $attributes['subject_notification_rationale'] ?? null, 'status' => 'remediation'];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function closureChanges(PrivacyIncident $incident, User $actor, array $attributes): array
    {
        abort_if(in_array($actor->id, [$incident->reported_by, $incident->assessed_by], true), 403, __('data-governance.privacy.errors.incident_closure_independence'));
        abort_unless($incident->documentLinks()->where('purpose', 'privacy-incident-closure-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists(), 409, __('data-governance.privacy.errors.incident_closure_evidence'));

        return ['closed_by' => $actor->id, 'root_cause' => $attributes['root_cause'], 'remediation_actions' => $attributes['remediation_actions'], 'closure_evidence_reference' => $attributes['closure_evidence_reference'], 'closed_at' => now(), 'status' => 'closed'];
    }
}
