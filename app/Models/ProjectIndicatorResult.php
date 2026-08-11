<?php

namespace App\Models;

use Database\Factories\ProjectIndicatorResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_progress_update_id', 'indicator_definition_id', 'county_id', 'period_start', 'period_end', 'dimension_key', 'disaggregation', 'numeric_value', 'narrative_value'])]
class ProjectIndicatorResult extends Model
{
    /** @use HasFactory<ProjectIndicatorResultFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['dimension_key' => 'total'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'disaggregation' => 'array', 'numeric_value' => 'decimal:6'];
    }

    /** @return BelongsTo<ProjectProgressUpdate, $this> */
    public function progressUpdate(): BelongsTo
    {
        return $this->belongsTo(ProjectProgressUpdate::class, 'project_progress_update_id');
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

    /** @return HasOne<IndicatorObservation, $this> */
    public function observation(): HasOne
    {
        return $this->hasOne(IndicatorObservation::class, 'source_project_indicator_result_id');
    }
}
