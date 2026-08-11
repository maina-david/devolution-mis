<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectProgressUpdateFactory;
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
 * @property string $devolution_project_id
 * @property string $submitted_by
 * @property string $verification_status
 * @property array<string, mixed> $provenance
 * @property Carbon $reporting_date
 * @property string $physical_progress
 * @property CarbonImmutable|null $verified_at
 */
#[Fillable(['devolution_project_id', 'reporting_date', 'physical_progress', 'financial_progress', 'narrative', 'achievements', 'challenges', 'next_steps', 'provenance', 'verification_status', 'verification_rationale', 'submitted_by', 'verified_by', 'verified_at'])]
class ProjectProgressUpdate extends Model
{
    /** @use HasFactory<ProjectProgressUpdateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['reporting_date' => 'date', 'physical_progress' => 'decimal:2', 'financial_progress' => 'decimal:2', 'provenance' => 'array', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }

    /** @return HasMany<ProjectIndicatorResult, $this> */
    public function indicatorResults(): HasMany
    {
        return $this->hasMany(ProjectIndicatorResult::class);
    }
}
