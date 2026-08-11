<?php

namespace App\Models;

use Database\Factories\DevolutionInnovationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decision_due_at
 * @property string|null $reference_data_release_id
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 */
#[Fillable(['workflow_instance_id', 'reference_data_release_id', 'county_id', 'sector_id', 'submitted_by', 'reviewed_by', 'reference', 'title', 'problem_statement', 'proposed_solution', 'expected_impact', 'maturity_level', 'stage', 'status', 'incubation_support', 'evidence_reference', 'submitted_at', 'decision_due_at', 'decided_at'])]
class DevolutionInnovation extends Model
{
    /** @use HasFactory<DevolutionInnovationFactory> */
    use HasFactory,HasUuids,SoftDeletes;

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'decision_due_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
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

    /** @return HasMany<InnovationPanelReview, $this> */
    public function panelReviews(): HasMany
    {
        return $this->hasMany(InnovationPanelReview::class);
    }

    /** @return HasMany<InnovationFundingDecision, $this> */
    public function fundingDecisions(): HasMany
    {
        return $this->hasMany(InnovationFundingDecision::class);
    }

    /** @return HasMany<InnovationExperimentMilestone, $this> */
    public function experimentMilestones(): HasMany
    {
        return $this->hasMany(InnovationExperimentMilestone::class);
    }

    /** @return HasMany<InnovationReplication, $this> */
    public function replications(): HasMany
    {
        return $this->hasMany(InnovationReplication::class);
    }
}
