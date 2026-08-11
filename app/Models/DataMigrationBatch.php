<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DataMigrationBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $period_from
 * @property CarbonImmutable $period_to
 * @property array{
 *     error_counts?: array<string, int>,
 *     reference_data_release?: array{id: string, version: int, status: string, checksum: string}
 * } $validation_report
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $applied_at
 */
#[Fillable(['reference', 'dataset_type', 'source_name', 'source_reference', 'period_from', 'period_to', 'original_name', 'mime_type', 'size_bytes', 'path', 'file_checksum', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'validation_report', 'submitted_by', 'reviewed_by', 'review_notes', 'reviewed_at', 'applied_by', 'applied_at'])]
class DataMigrationBatch extends Model
{
    /** @use HasFactory<DataMigrationBatchFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return HasMany<DataMigrationRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(DataMigrationRow::class);
    }

    /** @return HasMany<HistoricalMetric, $this> */
    public function historicalMetrics(): HasMany
    {
        return $this->hasMany(HistoricalMetric::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    protected function casts(): array
    {
        return [
            'period_from' => 'immutable_date',
            'period_to' => 'immutable_date',
            'validation_report' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
