<?php

namespace App\Models;

use Database\Factories\PerformanceGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['performance_plan_id', 'code', 'title', 'description', 'kpi', 'unit_of_measure', 'baseline_value', 'target_value', 'actual_value', 'weight', 'self_rating', 'supervisor_rating', 'employee_narrative', 'supervisor_comment', 'evidence_reference', 'sequence'])]
class PerformanceGoal extends Model
{
    /** @use HasFactory<PerformanceGoalFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['baseline_value' => 'decimal:4', 'target_value' => 'decimal:4', 'actual_value' => 'decimal:4', 'weight' => 'decimal:2', 'self_rating' => 'decimal:2', 'supervisor_rating' => 'decimal:2'];
    }

    /** @return BelongsTo<PerformancePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class, 'performance_plan_id');
    }

    /** @return HasMany<PerformanceGoalVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PerformanceGoalVersion::class)->latest('version');
    }

    /** @return HasMany<PerformanceGoalAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(PerformanceGoalAmendment::class)->latest('request_version');
    }
}
