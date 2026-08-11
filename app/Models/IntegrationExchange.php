<?php

namespace App\Models;

use Database\Factories\IntegrationExchangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $integration_contract_id
 * @property string|null $county_id
 * @property string|null $created_by
 * @property string|null $oauth_client_id
 * @property string $direction
 * @property string $correlation_id
 * @property string|null $external_reference
 * @property string $idempotency_key
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property array<string, mixed>|null $request_headers
 * @property string $payload_checksum
 * @property string $status
 * @property int|null $http_status
 * @property int $attempt_count
 * @property Carbon|null $next_attempt_at
 * @property string|null $error_category
 * @property string|null $error_detail
 * @property Carbon $accepted_at
 * @property Carbon|null $completed_at
 * @property-read Collection<int, IntegrationExchangeAttempt> $attempts
 * @property-read IntegrationContract $contract
 * @property-read County|null $county
 * @property-read User|null $creator
 * @property-read PartnerContributionSourceMatch|null $partnerContributionSourceMatch
 */
#[Fillable(['integration_contract_id', 'county_id', 'created_by', 'oauth_client_id', 'direction', 'correlation_id', 'external_reference', 'idempotency_key', 'request_payload', 'response_payload', 'request_headers', 'payload_checksum', 'status', 'http_status', 'attempt_count', 'next_attempt_at', 'source_occurred_at', 'accepted_at', 'processed_at', 'completed_at', 'error_category', 'error_detail'])]
class IntegrationExchange extends Model
{
    /** @use HasFactory<IntegrationExchangeFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['request_payload' => 'encrypted:array', 'response_payload' => 'encrypted:array', 'request_headers' => 'array', 'next_attempt_at' => 'immutable_datetime', 'source_occurred_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IntegrationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(IntegrationContract::class, 'integration_contract_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<PartnerContributionSourceMatch, $this> */
    public function partnerContributionSourceMatch(): HasOne
    {
        return $this->hasOne(PartnerContributionSourceMatch::class);
    }

    /** @return HasMany<IntegrationExchangeAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(IntegrationExchangeAttempt::class)->orderBy('attempt_number');
    }
}
