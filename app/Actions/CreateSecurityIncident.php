<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSecurityIncident
{
    public function __construct(private RecordSecurityIncidentEvent $recordEvent, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $reporter, array $attributes): SecurityIncident
    {
        abort_unless($reporter->can(ProgrammePermission::ManageSecurityGovernance->value), 403, __('security.incident_create.errors.create_unauthorized'));

        return DB::transaction(function () use ($reporter, $attributes): SecurityIncident {
            $severity = (string) $attributes['severity'];
            $sla = config("security-governance.incident_sla_minutes.{$severity}");
            abort_unless(is_array($sla) && is_numeric($sla['acknowledge'] ?? null) && is_numeric($sla['contain'] ?? null), 500, __('security.incident_create.errors.sla_unconfigured'));
            $detectedAt = now()->parse((string) $attributes['detected_at']);
            $recordType = (string) $attributes['record_type'];
            $incident = SecurityIncident::create([
                'reported_by' => $reporter->id,
                'incident_lead_id' => $attributes['incident_lead_id'],
                'reference' => 'SEC-'.($recordType === 'exercise' ? 'EXR' : 'LIVE').'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'record_type' => $recordType,
                'playbook' => $attributes['playbook'],
                'title' => $attributes['title'],
                'summary' => $attributes['summary'],
                'affected_services' => $this->csv((string) $attributes['affected_services']),
                'data_exposure' => $attributes['data_exposure'],
                'severity' => $severity,
                'business_impact' => $attributes['business_impact'] ?? null,
                'external_reference' => $attributes['external_reference'] ?? null,
                'exercise_objectives' => $recordType === 'exercise' ? $this->csv((string) $attributes['exercise_objectives']) : null,
                'detected_at' => $detectedAt,
                'acknowledgement_due_at' => $detectedAt->copy()->addMinutes((int) $sla['acknowledge']),
                'containment_due_at' => $detectedAt->copy()->addMinutes((int) $sla['contain']),
                'last_transition_at' => now(),
            ]);
            $this->recordEvent->handle($incident, $reporter, 'detect', 'none', 'detected', __($recordType === 'exercise' ? 'security.incident_create.event.exercise' : 'security.incident_create.event.live'), $attributes['external_reference'] ?? null);
            $this->auditLogger->record($reporter, $incident, 'security.incident.detected', __('security.incident_create.audit', ['type' => __('security.incident_create.types.'.$recordType), 'reference' => $incident->reference, 'playbook' => $incident->playbook]));

            return $incident->refresh();
        });
    }

    /** @return list<string> */
    private function csv(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value)), fn (string $item): bool => $item !== '')));
    }
}
