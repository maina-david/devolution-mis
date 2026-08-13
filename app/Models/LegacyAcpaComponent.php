<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LegacyAcpaComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $imported_at */
#[Fillable(['legacy_acpa_assessment_id', 'data_migration_batch_id', 'data_migration_row_id', 'record_type', 'record_reference', 'criterion_code', 'title', 'numeric_value', 'maximum_value', 'status', 'assignment_role', 'person_identifier', 'person_name', 'description', 'decision', 'file_name', 'mime_type', 'file_checksum', 'source_reference', 'source_payload', 'source_checksum', 'record_checksum', 'imported_by', 'imported_at'])]
class LegacyAcpaComponent extends Model
{
    /** @use HasFactory<LegacyAcpaComponentFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<LegacyAcpaAssessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(LegacyAcpaAssessment::class, 'legacy_acpa_assessment_id');
    }

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

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['numeric_value' => 'decimal:4', 'maximum_value' => 'decimal:4', 'source_payload' => 'encrypted:array', 'person_identifier' => 'encrypted', 'imported_at' => 'immutable_datetime'];
    }
}
