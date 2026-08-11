<?php

namespace App\Models;

use Database\Factories\InnovationReplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $target_completion_on
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property string|null $reference_data_release_id
 */
#[Fillable(['workflow_instance_id', 'devolution_innovation_id', 'source_county_id', 'target_county_id', 'reference_data_release_id', 'accountable_user_id', 'created_by', 'submitted_by', 'verified_by', 'reference', 'adaptation_plan', 'success_measure', 'baseline_value', 'target_value', 'actual_value', 'starts_on', 'target_completion_on', 'outcome_summary', 'status', 'verification_decision', 'verification_rationale', 'decision_checksum', 'submitted_at', 'verified_at'])]
class InnovationReplication extends Model
{
    /** @use HasFactory<InnovationReplicationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['baseline_value' => 'decimal:4', 'target_value' => 'decimal:4', 'actual_value' => 'decimal:4', 'starts_on' => 'immutable_date', 'target_completion_on' => 'immutable_date', 'submitted_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsTo<DevolutionInnovation, $this> */
    public function innovation(): BelongsTo
    {
        return $this->belongsTo(DevolutionInnovation::class, 'devolution_innovation_id');
    }

    /** @return BelongsTo<County, $this> */
    public function sourceCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'source_county_id');
    }

    /** @return BelongsTo<County, $this> */
    public function targetCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'target_county_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function accountableUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /** @return MorphMany<DocumentLink, $this> */
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'subject');
    }
}
