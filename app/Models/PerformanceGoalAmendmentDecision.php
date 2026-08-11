<?php

namespace App\Models;

use Database\Factories\PerformanceGoalAmendmentDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $decided_at
 */
#[Fillable(['performance_goal_amendment_id', 'decision', 'rationale', 'decided_by', 'decided_at', 'applied_version_id', 'decision_checksum', 'decision_snapshot'])]
class PerformanceGoalAmendmentDecision extends Model
{
    /** @use HasFactory<PerformanceGoalAmendmentDecisionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['decision_snapshot' => 'array', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PerformanceGoalAmendment, $this> */
    public function amendment(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoalAmendment::class, 'performance_goal_amendment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<PerformanceGoalVersion, $this> */
    public function appliedVersion(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoalVersion::class, 'applied_version_id');
    }
}
