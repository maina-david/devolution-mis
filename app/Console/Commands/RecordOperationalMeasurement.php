<?php

namespace App\Console\Commands;

use App\Actions\ReconcileOperationalMeasurements;
use App\Models\OperationalBackup;
use App\Models\ServiceLevelMeasurement;
use App\Services\OperationalReadinessCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('operations:measure')]
#[Description('Record readiness, queue and backup-recovery objective measurements')]
class RecordOperationalMeasurement extends Command
{
    public function handle(OperationalReadinessCheck $readiness, ReconcileOperationalMeasurements $reconcileAlerts): int
    {
        $observedAt = now();
        $result = $readiness->run();
        /** @var Collection<int, ServiceLevelMeasurement> $measurements */
        $measurements = new Collection;
        foreach ($result['checks'] as $name => $check) {
            $measurements->push(ServiceLevelMeasurement::create(['service' => config('operations.service_name'), 'metric' => "readiness.{$name}.latency", 'value' => $check['latency_ms'] ?? 0, 'unit' => 'milliseconds', 'target' => config('operations.readiness_latency_target_ms'), 'status' => $check['status'], 'observed_at' => $observedAt, 'metadata' => ['detail' => $check['detail']]]));
        }
        $queueDepth = DB::table(config('queue.connections.database.table', 'jobs'))->count();
        $measurements->push(ServiceLevelMeasurement::create(['service' => 'queue', 'metric' => 'depth', 'value' => $queueDepth, 'unit' => 'jobs', 'target' => config('operations.queue_depth_warning'), 'status' => $queueDepth <= config('operations.queue_depth_warning') ? 'pass' : 'warn', 'observed_at' => $observedAt]));
        $oldestCreatedAt = DB::table(config('queue.connections.database.table', 'jobs'))->min('created_at');
        $oldestAge = is_numeric($oldestCreatedAt) ? max(0, $observedAt->getTimestamp() - (int) $oldestCreatedAt) : 0;
        $measurements->push(ServiceLevelMeasurement::create(['service' => 'queue', 'metric' => 'oldest_job_age', 'value' => $oldestAge, 'unit' => 'seconds', 'target' => config('operations.queue_oldest_age_warning_seconds'), 'status' => $oldestAge <= config('operations.queue_oldest_age_warning_seconds') ? 'pass' : 'warn', 'observed_at' => $observedAt]));
        $failedJobs = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $measurements->push(ServiceLevelMeasurement::create(['service' => 'queue', 'metric' => 'failed_jobs', 'value' => $failedJobs, 'unit' => 'jobs', 'target' => config('operations.failed_jobs_warning'), 'status' => $failedJobs <= config('operations.failed_jobs_warning') ? 'pass' : 'fail', 'observed_at' => $observedAt]));
        $backup = OperationalBackup::query()->where('status', 'completed')->latest('completed_at')->first();
        $age = $backup?->completed_at?->diffInMinutes($observedAt) ?? config('operations.backup_max_age_minutes') + 1;
        $measurements->push(ServiceLevelMeasurement::create(['service' => 'database', 'metric' => 'backup_age', 'value' => $age, 'unit' => 'minutes', 'target' => config('operations.backup_max_age_minutes'), 'status' => $age <= config('operations.backup_max_age_minutes') ? 'pass' : 'fail', 'observed_at' => $observedAt, 'metadata' => ['backup_reference' => $backup?->reference]]));
        $alerts = $reconcileAlerts->handle($measurements);
        $this->line("Operational measurements recorded at {$observedAt->toIso8601String()}; alerts opened {$alerts['opened']}, repeated {$alerts['repeated']}, recovered {$alerts['recovered']}.");

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
