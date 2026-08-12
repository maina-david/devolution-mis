<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetentionScheduleApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $retention_schedule_id
 * @property string $submitted_by
 * @property string|null $reviewed_by
 * @property string $status
 * @property string|null $decision
 * @property string|null $decision_reason
 * @property string $snapshot_checksum
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property RetentionSchedule $retentionSchedule
 * @property User $submitter
 * @property User|null $reviewer
 */
#[Fillable(['retention_schedule_id', 'submitted_by', 'reviewed_by', 'status', 'decision', 'decision_reason', 'snapshot_checksum', 'submitted_at', 'reviewed_at'])]
class RetentionScheduleApproval extends Model
{
    /** @use HasFactory<RetentionScheduleApprovalFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<RetentionSchedule, $this> */
    public function retentionSchedule(): BelongsTo
    {
        return $this->belongsTo(RetentionSchedule::class);
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
}
