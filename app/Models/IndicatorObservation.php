<?php

namespace App\Models;

use Database\Factories\IndicatorObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $indicator_definition_id
 * @property string $county_id
 * @property string|null $programme_id
 * @property string $submitted_by
 * @property string $verification_status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string|null $numeric_value
 * @property string|null $narrative_value
 * @property string $measure_type
 * @property string $dimension_key
 * @property string|null $source_project_indicator_result_id
 * @property array<string, mixed> $provenance
 * @property string $quality_status
 * @property array<string, mixed>|null $disaggregation
 */
#[Fillable(['source_project_indicator_result_id', 'indicator_definition_id', 'county_id', 'programme_id', 'period_start', 'period_end', 'measure_type', 'dimension_key', 'disaggregation', 'numeric_value', 'narrative_value', 'source_reference', 'provenance', 'quality_status', 'quality_issues', 'verification_status', 'submitted_by', 'verified_by', 'submitted_at', 'verified_at'])]
class IndicatorObservation extends Model
{
    /** @use HasFactory<IndicatorObservationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['quality_status' => 'unassessed', 'verification_status' => 'submitted', 'dimension_key' => 'total'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'numeric_value' => 'decimal:6', 'disaggregation' => 'array', 'provenance' => 'array', 'quality_issues' => 'array', 'submitted_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IndicatorDefinition, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(IndicatorDefinition::class, 'indicator_definition_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<ProjectIndicatorResult, $this> */
    public function sourceProjectResult(): BelongsTo
    {
        return $this->belongsTo(ProjectIndicatorResult::class, 'source_project_indicator_result_id');
    }
}
