<?php

namespace App\Models;

use Database\Factories\AssessmentCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $assessment_scorecard_version_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $submission_opens_at
 * @property Carbon|null $submission_closes_at
 * @property string $status
 */
#[Fillable(['code', 'name', 'description', 'assessment_scorecard_version_id', 'period_start', 'period_end', 'submission_opens_at', 'submission_closes_at', 'status'])]
class AssessmentCycle extends Model
{
    /** @use HasFactory<AssessmentCycleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'submission_opens_at' => 'immutable_datetime', 'submission_closes_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AssessmentScorecardVersion, $this> */
    public function scorecardVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentScorecardVersion::class, 'assessment_scorecard_version_id');
    }
}
