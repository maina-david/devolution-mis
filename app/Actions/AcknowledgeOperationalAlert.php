<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\OperationalAlert;
use App\Models\OperationalAlertEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcknowledgeOperationalAlert
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    public function handle(OperationalAlert $alert, User $actor, string $note): OperationalAlert
    {
        abort_unless($actor->can(ProgrammePermission::ManageOperations->value), 403, __('operations.alert.errors.acknowledge_unauthorized'));

        $acknowledged = DB::transaction(function () use ($alert, $actor, $note): OperationalAlert {
            $locked = OperationalAlert::query()->whereKey($alert)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'recovered', 409, __('operations.alert.errors.recovered'));
            abort_if($locked->status === 'acknowledged', 409, __('operations.alert.errors.already_acknowledged'));

            $locked->fill(['status' => 'acknowledged', 'acknowledged_by' => $actor->id, 'acknowledged_at' => now(), 'acknowledgement_note' => $note]);
            $locked->evidence_checksum = $this->alertChecksum($locked);
            $locked->save();

            $eventId = (string) Str::uuid();
            $occurredAt = now()->startOfSecond();
            $payload = ['id' => $eventId, 'operational_alert_id' => $locked->id, 'measurement_id' => $locked->latest_measurement_id, 'actor_id' => $actor->id, 'event_type' => 'acknowledged', 'status' => $locked->status, 'narrative' => $note, 'occurred_at' => $occurredAt->toISOString()];
            OperationalAlertEvent::create([...$payload, 'evidence_checksum' => $this->canonicalJson->checksum($payload)]);

            return $locked;
        });

        $this->auditLogger->record($actor, $acknowledged, 'operations.alert.acknowledged', __('operations.alert.audit.acknowledged'));

        return $acknowledged;
    }

    private function alertChecksum(OperationalAlert $alert): string
    {
        return $this->canonicalJson->checksum([
            'id' => $alert->id,
            'initial_measurement_id' => $alert->initial_measurement_id,
            'latest_measurement_id' => $alert->latest_measurement_id,
            'recovery_measurement_id' => $alert->recovery_measurement_id,
            'service' => $alert->service,
            'metric' => $alert->metric,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'latest_value' => (string) $alert->latest_value,
            'threshold' => $alert->threshold === null ? null : (string) $alert->threshold,
            'unit' => $alert->unit,
            'occurrence_count' => $alert->occurrence_count,
            'first_detected_at' => $alert->first_detected_at->toISOString(),
            'last_detected_at' => $alert->last_detected_at->toISOString(),
            'acknowledged_by' => $alert->acknowledged_by,
            'acknowledged_at' => $alert->acknowledged_at?->toISOString(),
            'acknowledgement_note' => $alert->acknowledgement_note,
            'recovered_at' => $alert->recovered_at?->toISOString(),
        ]);
    }
}
