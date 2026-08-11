<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\HistoricalMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $period
 * @property CarbonImmutable $imported_at
 */
#[Fillable(['data_migration_batch_id', 'data_migration_row_id', 'county_id', 'dataset_type', 'period', 'metric_code', 'metric_name', 'numeric_value', 'narrative_value', 'unit', 'source_name', 'source_reference', 'source_checksum', 'record_checksum', 'imported_by', 'imported_at'])]
class HistoricalMetric extends Model
{
    /** @use HasFactory<HistoricalMetricFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<DataMigrationBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataMigrationBatch::class, 'data_migration_batch_id');
    }

    /** @return BelongsTo<DataMigrationRow, $this> */
    public function migrationRow(): BelongsTo
    {
        return $this->belongsTo(DataMigrationRow::class, 'data_migration_row_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    protected function casts(): array
    {
        return [
            'period' => 'immutable_date',
            'numeric_value' => 'decimal:4',
            'imported_at' => 'immutable_datetime',
        ];
    }
}
