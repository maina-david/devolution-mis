<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\QueueRecoveryAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class RetryFailedQueueJob
{
    public function __construct(private QueueManager $queueManager, private AuditLogger $auditLogger) {}

    public function handle(string $failedJobUuid, User $actor): QueueRecoveryAttempt
    {
        abort_unless($actor->can(ProgrammePermission::ManageOperations->value), 403, __('operations.queue.errors.retry_unauthorized'));
        abort_unless(Str::isUuid($failedJobUuid), 404, __('operations.queue.errors.failed_job_missing'));

        return DB::transaction(function () use ($failedJobUuid, $actor): QueueRecoveryAttempt {
            $failedJob = DB::table('failed_jobs')->where('uuid', $failedJobUuid)->lockForUpdate()->first();
            abort_if($failedJob === null, 404, __('operations.queue.errors.failed_job_missing'));

            $payload = (string) $failedJob->payload;
            $exception = (string) $failedJob->exception;
            $connection = (string) $failedJob->connection;
            abort_unless(config("queue.connections.{$connection}.driver") === 'database', 409, __('operations.queue.errors.provider_unsupported'));
            $retryPayload = $this->resetAttempts($payload);
            $attemptedAt = now();
            $outcome = 'requeued';
            $errorCategory = null;
            $errorDetail = null;

            try {
                $this->queueManager->connection($connection)->pushRaw($retryPayload, (string) $failedJob->queue);
                DB::table('failed_jobs')->where('uuid', $failedJobUuid)->delete();
            } catch (Throwable $exceptionThrown) {
                $outcome = 'retry_failed';
                $errorCategory = class_basename($exceptionThrown);
                $errorDetail = __('operations.queue.errors.provider_rejected_detail');
            }

            $evidence = [
                'failed_job_uuid' => $failedJobUuid,
                'initiated_by' => $actor->id,
                'connection' => $connection,
                'queue' => (string) $failedJob->queue,
                'job_name' => $this->jobName($payload),
                'payload_checksum' => hash('sha256', $payload),
                'exception_checksum' => hash('sha256', $exception),
                'outcome' => $outcome,
                'attempted_at' => $attemptedAt->toIso8601String(),
            ];
            $attempt = QueueRecoveryAttempt::create([
                ...$evidence,
                'initiated_by_name' => $actor->name,
                'error_category' => $errorCategory,
                'error_detail' => $errorDetail,
                'failed_at' => Carbon::parse((string) $failedJob->failed_at),
                'attempted_at' => $attemptedAt,
                'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ]);
            $this->auditLogger->record($actor, $attempt, 'operations.queue.recovery_attempted', __('operations.audit.queue_recovery_attempted', ['uuid' => $failedJobUuid, 'outcome' => __('operations.queue.outcomes.'.$outcome)]), null, ['failed_job_uuid' => $failedJobUuid, 'outcome' => $outcome, 'evidence_checksum' => $attempt->evidence_checksum]);

            return $attempt;
        });
    }

    private function resetAttempts(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(409, __('operations.queue.errors.invalid_json'));
        }
        abort_unless(is_array($decoded), 409, __('operations.queue.errors.invalid_payload'));
        if (array_key_exists('attempts', $decoded)) {
            $decoded['attempts'] = 0;
        }

        return json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function jobName(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return __('operations.labels.unknown_queued_job');
        }
        $name = is_array($decoded) ? ($decoded['displayName'] ?? $decoded['job'] ?? null) : null;

        return is_string($name) ? Str::limit($name, 255, '') : __('operations.labels.unknown_queued_job');
    }
}
