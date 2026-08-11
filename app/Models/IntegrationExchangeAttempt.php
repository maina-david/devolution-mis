<?php

namespace App\Models;

use Database\Factories\IntegrationExchangeAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $integration_exchange_id
 * @property string|null $initiated_by
 * @property string|null $initiated_by_name
 * @property int $attempt_number
 * @property string $trigger_source
 * @property string $outcome
 * @property int|null $http_status
 * @property bool $retryable
 * @property int|null $retry_after_seconds
 * @property string|null $response_checksum
 * @property string|null $error_category
 * @property string|null $error_detail
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property int $duration_ms
 */
#[Fillable(['integration_exchange_id', 'initiated_by', 'initiated_by_name', 'attempt_number', 'trigger_source', 'outcome', 'http_status', 'retryable', 'retry_after_seconds', 'response_checksum', 'error_category', 'error_detail', 'started_at', 'completed_at', 'duration_ms'])]
class IntegrationExchangeAttempt extends Model
{
    /** @use HasFactory<IntegrationExchangeAttemptFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['retryable' => 'boolean', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IntegrationExchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(IntegrationExchange::class, 'integration_exchange_id');
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
