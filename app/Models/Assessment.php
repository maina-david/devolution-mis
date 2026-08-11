<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $county_id
 * @property string|null $assessment_cycle_id
 * @property string|null $assessment_scorecard_version_id
 * @property string|null $reference_data_release_id
 * @property string $cycle
 * @property AssessmentStatus $status
 * @property string|null $score
 * @property string|null $assessor_id
 * @property Carbon|null $assessed_at
 */
#[Fillable(['county_id', 'assessment_cycle_id', 'assessment_scorecard_version_id', 'reference_data_release_id', 'cycle', 'status', 'score', 'completeness_percentage', 'attestation_status', 'assessor_id', 'created_by', 'approved_by', 'published_by', 'assessed_at', 'approved_at', 'published_at'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    /** @return BelongsTo<AssessmentCycle, $this> */
    public function assessmentCycle(): BelongsTo
    {
        return $this->belongsTo(AssessmentCycle::class);
    }

    /** @return BelongsTo<AssessmentScorecardVersion, $this> */
    public function scorecardVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentScorecardVersion::class, 'assessment_scorecard_version_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AssessmentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AssessmentDocument::class);
    }

    /** @return HasMany<AssessmentCriterionResult, $this> */
    public function criterionResults(): HasMany
    {
        return $this->hasMany(AssessmentCriterionResult::class);
    }

    /** @return HasMany<AssessmentFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(AssessmentFinding::class);
    }

    /** @return HasMany<AssessmentAttestation, $this> */
    public function attestations(): HasMany
    {
        return $this->hasMany(AssessmentAttestation::class);
    }

    /** @return HasMany<AssessmentAppeal, $this> */
    public function appeals(): HasMany
    {
        return $this->hasMany(AssessmentAppeal::class);
    }

    /** @return HasMany<AssessmentCorrectivePlan, $this> */
    public function correctivePlans(): HasMany
    {
        return $this->hasMany(AssessmentCorrectivePlan::class);
    }

    /** @return HasOne<AssessmentResultPublication, $this> */
    public function publication(): HasOne
    {
        return $this->hasOne(AssessmentResultPublication::class);
    }

    protected function casts(): array
    {
        return ['status' => AssessmentStatus::class, 'score' => 'decimal:2', 'completeness_percentage' => 'decimal:2', 'assessed_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }
}
