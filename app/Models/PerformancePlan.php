<?php

namespace App\Models;

use Database\Factories\PerformancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $decision_due_at
 * @property int $goals_count
 * @property int $goal_versions_count
 * @property int $goal_amendments_count
 * @property int $pending_goal_amendments_count
 * @property string|null $reference_data_release_id
 */
#[Fillable(['performance_cycle_id', 'workflow_instance_id', 'reference_data_release_id', 'employee_id', 'supervisor_id', 'organization_id', 'plan_type', 'hris_employee_reference', 'job_title', 'overall_expectations', 'status', 'self_score', 'supervisor_score', 'final_score', 'capacity_gap_summary', 'integration_status', 'integration_metadata', 'submitted_at', 'decision_due_at', 'reminder_sent_at', 'escalated_at', 'finalized_at', 'created_by'])]
class PerformancePlan extends Model
{
    /** @use HasFactory<PerformancePlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['self_score' => 'decimal:2', 'supervisor_score' => 'decimal:2', 'final_score' => 'decimal:2', 'integration_metadata' => 'array', 'submitted_at' => 'immutable_datetime', 'decision_due_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PerformanceCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return HasMany<PerformanceGoal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    /** @return HasMany<PerformanceReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    /** @return HasMany<PerformanceGoalAmendment, $this> */
    public function goalAmendments(): HasMany
    {
        return $this->hasMany(PerformanceGoalAmendment::class);
    }

    /** @return HasManyThrough<PerformanceGoalVersion, PerformanceGoal, $this> */
    public function goalVersions(): HasManyThrough
    {
        return $this->hasManyThrough(PerformanceGoalVersion::class, PerformanceGoal::class, 'performance_plan_id', 'performance_goal_id');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
