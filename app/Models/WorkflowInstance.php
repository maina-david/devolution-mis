<?php

namespace App\Models;

use Database\Factories\WorkflowInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workflow_version_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $county_id
 * @property string|null $business_calendar_id
 * @property string $current_state
 * @property string $status
 * @property array<string, mixed> $context
 * @property string|null $started_by
 * @property Carbon $started_at
 * @property Carbon $state_entered_at
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['workflow_version_id', 'subject_type', 'subject_id', 'county_id', 'business_calendar_id', 'current_state', 'status', 'context', 'started_by', 'started_at', 'state_entered_at', 'due_at', 'completed_at'])]
class WorkflowInstance extends Model
{
    /** @use HasFactory<WorkflowInstanceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'immutable_datetime',
            'state_entered_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<WorkflowVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /** @return HasMany<WorkflowEscalation, $this> */
    public function escalations(): HasMany
    {
        return $this->hasMany(WorkflowEscalation::class);
    }
}
