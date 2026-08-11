<?php

namespace App\Models;

use Database\Factories\PerformanceGoalAmendmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $request_version
 * @property array<string, string|null> $proposed_snapshot
 * @property string $request_checksum
 * @property Carbon $requested_at
 */
#[Fillable(['performance_plan_id', 'performance_goal_id', 'base_version_id', 'request_version', 'proposed_snapshot', 'reason', 'requested_by', 'requested_at', 'predecessor_checksum', 'request_checksum'])]
class PerformanceGoalAmendment extends Model
{
    /** @use HasFactory<PerformanceGoalAmendmentFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['proposed_snapshot' => 'array', 'requested_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PerformancePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class, 'performance_plan_id');
    }

    /** @return BelongsTo<PerformanceGoal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'performance_goal_id');
    }

    /** @return BelongsTo<PerformanceGoalVersion, $this> */
    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoalVersion::class, 'base_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasOne<PerformanceGoalAmendmentDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(PerformanceGoalAmendmentDecision::class);
    }
}
