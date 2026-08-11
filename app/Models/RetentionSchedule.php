<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RetentionScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $approved_by
 * @property string $code
 * @property string $record_class
 * @property string $trigger_event
 * @property int $retention_months
 * @property string $disposition_action
 * @property string $legal_authority
 * @property string $legal_hold_rule
 * @property string $status
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $next_review_at
 * @property User|null $approver
 */
#[Fillable(['approved_by', 'code', 'record_class', 'trigger_event', 'retention_months', 'disposition_action', 'legal_authority', 'legal_hold_rule', 'status', 'effective_from', 'approved_at', 'next_review_at'])]
class RetentionSchedule extends Model
{
    /** @use HasFactory<RetentionScheduleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['retention_months' => 'integer', 'effective_from' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'next_review_at' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ProcessingActivity, $this> */
    public function processingActivities(): HasMany
    {
        return $this->hasMany(ProcessingActivity::class);
    }
}
