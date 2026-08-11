<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InnovationExperimentMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $devolution_innovation_id
 * @property string $owner_id
 * @property string|null $assessment_document_id
 * @property string $title
 * @property string $hypothesis
 * @property string $success_metric
 * @property string $baseline_value
 * @property string $target_value
 * @property CarbonImmutable|null $due_at
 * @property string $status
 * @property string|null $actual_value
 * @property string|null $outcome_summary
 * @property string|null $submitted_by
 * @property CarbonImmutable|null $submitted_at
 * @property string $verification_decision
 * @property string|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $verification_rationale
 * @property DevolutionInnovation $innovation
 * @property User $owner
 * @property User|null $submitter
 * @property User|null $verifier
 * @property AssessmentDocument|null $document
 */
#[Fillable(['devolution_innovation_id', 'owner_id', 'assessment_document_id', 'title', 'hypothesis', 'success_metric', 'baseline_value', 'target_value', 'due_at', 'status', 'actual_value', 'outcome_summary', 'submitted_by', 'submitted_at', 'verification_decision', 'verified_by', 'verified_at', 'verification_rationale'])]
class InnovationExperimentMilestone extends Model
{
    /** @use HasFactory<InnovationExperimentMilestoneFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned', 'verification_decision' => 'pending'];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_date', 'submitted_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DevolutionInnovation, $this> */
    public function innovation(): BelongsTo
    {
        return $this->belongsTo(DevolutionInnovation::class, 'devolution_innovation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
