<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\OperationalAlert;
use App\Models\OperationalAlertEvent;
use App\Models\ServiceLevelMeasurement;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReconcileOperationalMeasurements
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /**
     * @param  Collection<int, ServiceLevelMeasurement>  $measurements
     * @return array{opened: int, repeated: int, recovered: int}
     */
    public function handle(Collection $measurements): array
    {
        $outcomes = ['opened' => 0, 'repeated' => 0, 'recovered' => 0];

        foreach ($measurements as $measurement) {
            $result = $this->reconcile($measurement);
            if ($result === null) {
                continue;
            }

            $outcomes[$result['event']]++;
            if (in_array($result['event'], ['opened', 'recovered'], true)) {
                $this->auditLogger->record(null, $result['alert'], "operations.alert.{$result['event']}", $this->narrative($measurement, $result['event']));
                $this->notifyOperators($result['alert'], $result['event']);
            }
        }

        return $outcomes;
    }

    /** @return array{alert: OperationalAlert, event: 'opened'|'repeated'|'recovered'}|null */
    private function reconcile(ServiceLevelMeasurement $measurement): ?array
    {
        return DB::transaction(function () use ($measurement): ?array {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["{$measurement->service}:{$measurement->metric}"]);
            $activeAlert = OperationalAlert::query()
                ->where('service', $measurement->service)
                ->where('metric', $measurement->metric)
                ->whereIn('status', ['open', 'acknowledged'])
                ->lockForUpdate()
                ->first();

            if ($measurement->status === 'pass') {
                if (! $activeAlert) {
                    return null;
                }

                $activeAlert->fill([
                    'latest_measurement_id' => $measurement->id,
                    'recovery_measurement_id' => $measurement->id,
                    'latest_value' => $measurement->value,
                    'threshold' => $measurement->target,
                    'status' => 'recovered',
                    'recovered_at' => $measurement->observed_at,
                ]);
                $activeAlert->evidence_checksum = $this->alertChecksum($activeAlert);
                $activeAlert->save();
                $this->recordEvent($activeAlert, $measurement, null, 'recovered', $this->narrative($measurement, 'recovered'));

                return ['alert' => $activeAlert, 'event' => 'recovered'];
            }

            if ($activeAlert) {
                $activeAlert->fill([
                    'latest_measurement_id' => $measurement->id,
                    'latest_value' => $measurement->value,
                    'threshold' => $measurement->target,
                    'severity' => $this->severity($measurement),
                    'last_detected_at' => $measurement->observed_at,
                    'occurrence_count' => $activeAlert->occurrence_count + 1,
                ]);
                $activeAlert->evidence_checksum = $this->alertChecksum($activeAlert);
                $activeAlert->save();
                $this->recordEvent($activeAlert, $measurement, null, 'repeated', $this->narrative($measurement, 'repeated'));

                return ['alert' => $activeAlert, 'event' => 'repeated'];
            }

            $alert = new OperationalAlert([
                'initial_measurement_id' => $measurement->id,
                'latest_measurement_id' => $measurement->id,
                'service' => $measurement->service,
                'metric' => $measurement->metric,
                'severity' => $this->severity($measurement),
                'status' => 'open',
                'latest_value' => $measurement->value,
                'threshold' => $measurement->target,
                'unit' => $measurement->unit,
                'occurrence_count' => 1,
                'first_detected_at' => $measurement->observed_at,
                'last_detected_at' => $measurement->observed_at,
            ]);
            $alert->id = (string) Str::uuid();
            $alert->evidence_checksum = $this->alertChecksum($alert);
            $alert->save();
            $this->recordEvent($alert, $measurement, null, 'opened', $this->narrative($measurement, 'opened'));

            return ['alert' => $alert, 'event' => 'opened'];
        });
    }

    private function recordEvent(OperationalAlert $alert, ServiceLevelMeasurement $measurement, ?User $actor, string $eventType, string $narrative): void
    {
        $id = (string) Str::uuid();
        $occurredAt = now()->startOfSecond();
        $payload = ['id' => $id, 'operational_alert_id' => $alert->id, 'measurement_id' => $measurement->id, 'actor_id' => $actor?->id, 'event_type' => $eventType, 'status' => $alert->status, 'narrative' => $narrative, 'occurred_at' => $occurredAt->toISOString()];
        OperationalAlertEvent::create([...$payload, 'evidence_checksum' => $this->canonicalJson->checksum($payload)]);
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

    private function severity(ServiceLevelMeasurement $measurement): string
    {
        return $measurement->status === 'fail' ? 'critical' : 'warning';
    }

    private function narrative(ServiceLevelMeasurement $measurement, string $event): string
    {
        $target = $measurement->target === null ? 'no configured target' : "target {$measurement->target} {$measurement->unit}";

        return Str::headline($measurement->metric)." {$event}: {$measurement->value} {$measurement->unit}; {$target}.";
    }

    private function notifyOperators(OperationalAlert $alert, string $event): void
    {
        User::permission(ProgrammePermission::ManageOperations->value)
            ->whereNull('access_revoked_at')
            ->whereNotNull('current_team_id')
            ->with('currentTeam:id,slug')
            ->get()
            ->each(function (User $user) use ($alert, $event): void {
                $team = $user->currentTeam;
                if (! $team) {
                    return;
                }

                Notification::send($user, new ProgrammeAlert(
                    $event === 'opened' ? 'Operational threshold breached' : 'Operational service recovered',
                    Str::headline($alert->metric)." is {$alert->status} at {$alert->latest_value} {$alert->unit}.",
                    'operations',
                    route('operations.index', $team->slug),
                ));
            });
    }
}
