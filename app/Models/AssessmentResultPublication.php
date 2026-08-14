<?php

namespace App\Models;

use Database\Factories\AssessmentResultPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $assessment_id
 * @property string $assessment_cycle_id
 * @property string $county_id
 * @property string $score
 * @property string $performance_band
 * @property list<array{function_id: string, code: string, name: string, weight: float, score: float, weighted_contribution: float}> $function_profile
 * @property array<string, mixed> $calculation_snapshot
 * @property string $checksum
 * @property Carbon $published_at
 */
#[Fillable(['assessment_id', 'assessment_cycle_id', 'assessment_scorecard_version_id', 'county_id', 'score', 'performance_band', 'function_profile', 'calculation_snapshot', 'checksum', 'published_by', 'published_at'])]
class AssessmentResultPublication extends Model
{
    /** @use HasFactory<AssessmentResultPublicationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'function_profile' => 'array', 'calculation_snapshot' => 'array', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<AssessmentCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AssessmentCycle::class, 'assessment_cycle_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
