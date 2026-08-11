<?php

namespace App\Models;

use Database\Factories\ReconciliationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $source_count
 * @property int $target_count
 * @property int $matched_count
 * @property int $exception_count
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['integration_system_id', 'integration_contract_id', 'initiated_by', 'reference', 'period_from', 'period_to', 'source_count', 'target_count', 'matched_count', 'exception_count', 'source_total', 'target_total', 'status', 'result_checksum', 'started_at', 'completed_at', 'metadata'])]
class ReconciliationRun extends Model
{
    /** @use HasFactory<ReconciliationRunFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['period_from' => 'immutable_date', 'period_to' => 'immutable_date', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<IntegrationSystem, $this> */
    public function system(): BelongsTo
    {
        return $this->belongsTo(IntegrationSystem::class, 'integration_system_id');
    }

    /** @return BelongsTo<IntegrationContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(IntegrationContract::class, 'integration_contract_id');
    }

    /** @return HasMany<ReconciliationException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class);
    }

    /** @return HasMany<PartnerContributionSourceMatch, $this> */
    public function partnerContributionSourceMatches(): HasMany
    {
        return $this->hasMany(PartnerContributionSourceMatch::class);
    }
}
