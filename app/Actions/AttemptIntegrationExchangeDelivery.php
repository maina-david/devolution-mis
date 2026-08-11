<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IntegrationExchange;
use App\Models\IntegrationExchangeAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IntegrationTransportManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AttemptIntegrationExchangeDelivery
{
    public function __construct(private IntegrationTransportManager $transport, private AuditLogger $auditLogger) {}

    public function handle(IntegrationExchange $exchange, User $actor, string $triggerSource): IntegrationExchange
    {
        abort_unless($actor->can(ProgrammePermission::ManageIntegrations->value), 403);
        abort_unless(in_array($triggerSource, ['initial_dispatch', 'scheduled_retry', 'manual_retry'], true), 422, 'Unsupported retry trigger.');

        $claim = DB::transaction(function () use ($exchange, $triggerSource): array {
            $current = IntegrationExchange::query()->with('contract.system')->lockForUpdate()->findOrFail($exchange->id);
            abort_unless($current->direction === 'outbound', 409, 'Only outbound exchanges can be delivered.');

            $allowedStatuses = match ($triggerSource) {
                'initial_dispatch' => ['accepted'],
                'scheduled_retry' => ['retry_scheduled'],
                'manual_retry' => ['retry_scheduled', 'dead_lettered'],
            };
            abort_unless(in_array($current->status, $allowedStatuses, true), 409, 'The exchange is not eligible for this delivery action.');
            if ($triggerSource === 'scheduled_retry') {
                abort_unless($current->next_attempt_at === null || $current->next_attempt_at->isPast(), 409, 'The scheduled retry is not due yet.');
            }

            $attemptNumber = $current->attempt_count + 1;
            $current->update(['status' => 'processing', 'attempt_count' => $attemptNumber, 'processed_at' => now(), 'next_attempt_at' => null, 'completed_at' => null]);

            return ['exchange' => $current, 'attempt_number' => $attemptNumber, 'started_at' => now(), 'started_ns' => hrtime(true)];
        });

        /** @var IntegrationExchange $current */
        $current = $claim['exchange'];
        $attemptNumber = (int) $claim['attempt_number'];
        $startedAt = $claim['started_at'];
        $startedNs = (int) $claim['started_ns'];
        $contract = $current->contract;
        $response = null;
        $httpStatus = null;
        $responseChecksum = null;
        $errorCategory = null;
        $errorDetail = null;
        $retryable = false;

        try {
            $response = $this->transport->send($contract, $current->request_payload, $current->correlation_id);
            $httpStatus = $response['status'];
            $responseChecksum = hash('sha256', json_encode($this->canonicalize($response['body']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $retryable = in_array($httpStatus, [408, 425, 429], true) || $httpStatus >= 500;
            if ($httpStatus < 200 || $httpStatus >= 300) {
                $errorCategory = 'remote_rejection';
                $errorDetail = "Remote endpoint returned HTTP {$httpStatus}.";
            }
        } catch (Throwable $exception) {
            $retryable = $exception instanceof ConnectionException;
            $errorCategory = $exception instanceof HttpExceptionInterface ? 'configuration' : 'transport';
            $errorDetail = mb_substr($exception->getMessage(), 0, 5000);
        }

        $succeeded = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $maxAttempts = max(1, (int) ($contract->retry_policy['max_attempts'] ?? 1));
        $canRetry = ! $succeeded && $retryable && $attemptNumber < $maxAttempts;
        $retryAfterSeconds = $canRetry ? $this->backoffSeconds($contract->retry_policy, $attemptNumber) : null;
        $outcome = $succeeded ? 'succeeded' : ($canRetry ? 'retry_scheduled' : 'dead_lettered');
        $completedAt = now();
        $durationMs = max(0, (int) round((hrtime(true) - $startedNs) / 1_000_000));

        DB::transaction(function () use ($current, $actor, $triggerSource, $attemptNumber, $startedAt, $completedAt, $durationMs, $response, $httpStatus, $responseChecksum, $errorCategory, $errorDetail, $retryable, $retryAfterSeconds, $outcome): void {
            $locked = IntegrationExchange::query()->lockForUpdate()->findOrFail($current->id);
            abort_unless($locked->status === 'processing' && $locked->attempt_count === $attemptNumber, 409, 'The exchange delivery claim changed before completion.');

            IntegrationExchangeAttempt::create([
                'integration_exchange_id' => $locked->id,
                'initiated_by' => $actor->id,
                'initiated_by_name' => $actor->name,
                'attempt_number' => $attemptNumber,
                'trigger_source' => $triggerSource,
                'outcome' => $outcome,
                'http_status' => $httpStatus,
                'retryable' => $retryable,
                'retry_after_seconds' => $retryAfterSeconds,
                'response_checksum' => $responseChecksum,
                'error_category' => $errorCategory,
                'error_detail' => $errorDetail,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'duration_ms' => $durationMs,
            ]);

            $locked->update([
                'response_payload' => $response['body'] ?? null,
                'http_status' => $httpStatus,
                'status' => $outcome,
                'next_attempt_at' => $retryAfterSeconds !== null ? $completedAt->addSeconds($retryAfterSeconds) : null,
                'completed_at' => $outcome === 'retry_scheduled' ? null : $completedAt,
                'error_category' => $errorCategory,
                'error_detail' => $errorDetail,
            ]);
        });

        $fresh = $current->refresh();
        $this->auditLogger->record($actor, $fresh, 'integration.exchange.delivery_attempted', "Exchange {$fresh->correlation_id} attempt {$attemptNumber} completed as {$outcome}.", $fresh->county_id, ['attempt_number' => $attemptNumber, 'trigger_source' => $triggerSource, 'outcome' => $outcome, 'http_status' => $httpStatus, 'retry_after_seconds' => $retryAfterSeconds]);

        return $fresh;
    }

    /** @param array<string, mixed> $retryPolicy */
    private function backoffSeconds(array $retryPolicy, int $attemptNumber): int
    {
        $backoffs = array_values(array_filter((array) ($retryPolicy['backoff_seconds'] ?? []), is_int(...)));

        return max(1, (int) ($backoffs[$attemptNumber - 1] ?? end($backoffs) ?: 60));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalize(array $payload): array
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value) && ! array_is_list($value)) {
                $value = $this->canonicalize($value);
            }
        }

        return $payload;
    }
}
