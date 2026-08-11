<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DataMigrationRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable|null $period
 * @property CarbonImmutable|null $applied_at
 * @property array<string, string>|null $source_payload
 * @property list<string>|null $validation_errors
 */
#[Fillable(['data_migration_batch_id', 'row_number', 'county_id', 'period', 'metric_code', 'metric_name', 'numeric_value', 'narrative_value', 'unit', 'source_reference', 'source_payload', 'source_checksum', 'validation_status', 'validation_errors', 'applied_at'])]
class DataMigrationRow extends Model
{
    /** @use HasFactory<DataMigrationRowFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<DataMigrationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataMigrationBatch::class, 'data_migration_batch_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasOne<HistoricalMetric, $this> */
    public function historicalMetric(): HasOne
    {
        return $this->hasOne(HistoricalMetric::class);
    }

    protected function casts(): array
    {
        return [
            'period' => 'immutable_date',
            'numeric_value' => 'decimal:4',
            'source_payload' => 'encrypted:array',
            'validation_errors' => 'array',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
