<?php

namespace App\Models;

use Database\Factories\IntegrationContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $integration_system_id
 * @property string $status
 * @property string $resource_name
 * @property string $http_method
 * @property string $path
 * @property int $rate_limit_per_minute
 * @property array<int, string>|null $required_headers
 * @property string|null $source_owner_approval_reference
 * @property string|null $data_sharing_agreement_reference
 * @property-read IntegrationSystem $system
 * @property array<string, mixed> $request_schema
 * @property array<string, mixed>|null $response_schema
 * @property array<string, mixed>|null $field_mappings
 * @property array<string, mixed> $retry_policy
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
#[Fillable(['integration_system_id', 'submitted_by', 'approved_by', 'version', 'name', 'resource_name', 'http_method', 'path', 'request_schema', 'response_schema', 'field_mappings', 'required_headers', 'idempotency_field', 'retry_policy', 'rate_limit_per_minute', 'status', 'content_checksum', 'source_owner_approval_reference', 'data_sharing_agreement_reference', 'effective_from', 'effective_to', 'published_at'])]
class IntegrationContract extends Model
{
    /** @use HasFactory<IntegrationContractFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['request_schema' => 'array', 'response_schema' => 'array', 'field_mappings' => 'array', 'required_headers' => 'array', 'retry_policy' => 'array', 'effective_from' => 'immutable_datetime', 'effective_to' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IntegrationSystem, $this> */
    public function system(): BelongsTo
    {
        return $this->belongsTo(IntegrationSystem::class, 'integration_system_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<IntegrationExchange, $this> */
    public function exchanges(): HasMany
    {
        return $this->hasMany(IntegrationExchange::class);
    }
}
