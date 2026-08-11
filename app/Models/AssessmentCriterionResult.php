<?php

namespace App\Models;

use Database\Factories\AssessmentCriterionResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $verified_score
 * @property string|null $override_score
 * @property string $assessment_id
 * @property string|null $scored_by
 * @property string|null $overridden_by
 * @property string|null $weighted_score
 * @property array<string, mixed>|null $calculation_snapshot
 */
#[Fillable(['assessment_id', 'assessment_criterion_id', 'submitted_score', 'verified_score', 'override_score', 'weighted_score', 'submission_rationale', 'verification_rationale', 'override_reason', 'scored_by', 'verified_by', 'overridden_by', 'verified_at', 'calculation_snapshot'])]
class AssessmentCriterionResult extends Model
{
    /** @use HasFactory<AssessmentCriterionResultFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['submitted_score' => 'decimal:4', 'verified_score' => 'decimal:4', 'override_score' => 'decimal:4', 'weighted_score' => 'decimal:6', 'verified_at' => 'immutable_datetime', 'calculation_snapshot' => 'array'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<AssessmentCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssessmentCriterion::class, 'assessment_criterion_id');
    }
}
