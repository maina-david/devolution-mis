<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LegacyAcpaAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $period
 * @property CarbonImmutable $imported_at
 * @property-read Collection<int, LegacyAcpaComponent> $components
 */
#[Fillable(['data_migration_batch_id', 'data_migration_row_id', 'county_id', 'assessment_reference', 'period', 'cycle_name', 'status', 'overall_score', 'source_name', 'source_reference', 'source_checksum', 'record_checksum', 'imported_by', 'imported_at'])]
class LegacyAcpaAssessment extends Model
{
    /** @use HasFactory<LegacyAcpaAssessmentFactory> */
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

    /** @return HasMany<LegacyAcpaComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(LegacyAcpaComponent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period' => 'immutable_date', 'overall_score' => 'decimal:4', 'imported_at' => 'immutable_datetime'];
    }
}
