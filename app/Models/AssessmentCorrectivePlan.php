<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AssessmentCorrectivePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $assessment_id
 * @property string $county_id
 * @property string|null $assessment_finding_id
 * @property string|null $assessment_appeal_id
 * @property string $submitted_by
 * @property string|null $reviewed_by
 * @property string|null $closed_by
 * @property string $reference
 * @property string $title
 * @property string $root_cause
 * @property string $expected_outcome
 * @property string $status
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property CarbonImmutable|null $closure_requested_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $closure_decision
 * @property string $checksum
 * @property-read Assessment $assessment
 * @property-read County $county
 * @property-read AssessmentFinding|null $finding
 * @property-read AssessmentAppeal|null $appeal
 * @property-read User $submitter
 * @property-read User|null $reviewer
 * @property-read User|null $closer
 * @property-read Collection<int, AssessmentCorrectiveAction> $actions
 */
#[Fillable(['assessment_id', 'county_id', 'assessment_finding_id', 'assessment_appeal_id', 'submitted_by', 'reviewed_by', 'closed_by', 'reference', 'title', 'root_cause', 'expected_outcome', 'status', 'due_at', 'submitted_at', 'reviewed_at', 'review_note', 'closure_requested_at', 'closed_at', 'closure_decision', 'checksum'])]

class AssessmentCorrectivePlan extends Model
{
    /** @use HasFactory<AssessmentCorrectivePlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['due_at' => 'date', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'closure_requested_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<AssessmentFinding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AssessmentFinding::class, 'assessment_finding_id');
    }

    /** @return BelongsTo<AssessmentAppeal, $this> */
    public function appeal(): BelongsTo
    {
        return $this->belongsTo(AssessmentAppeal::class, 'assessment_appeal_id');
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
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<AssessmentCorrectiveAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(AssessmentCorrectiveAction::class);
    }
}
