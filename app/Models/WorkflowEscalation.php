<?php

namespace App\Models;

use Database\Factories\WorkflowEscalationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workflow_instance_id
 * @property int $level
 * @property string $reason
 * @property string $status
 * @property string|null $escalated_to
 * @property Carbon|null $due_at
 * @property Carbon $state_entered_at
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property array<string, mixed> $metadata
 */
#[Fillable(['workflow_instance_id', 'level', 'reason', 'status', 'escalated_to', 'due_at', 'state_entered_at', 'triggered_at', 'acknowledged_at', 'resolved_at', 'metadata'])]
class WorkflowEscalation extends Model
{
    /** @use HasFactory<WorkflowEscalationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['level' => 1, 'status' => 'open'];

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'state_entered_at' => 'immutable_datetime',
            'triggered_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
