<?php

namespace App\Models;

use Database\Factories\WorkflowTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workflow_instance_id
 * @property string $transition_name
 * @property string|null $from_state
 * @property string $to_state
 * @property string|null $actor_id
 * @property string|null $comment
 * @property array<string, mixed> $rule_evaluation
 * @property array<string, mixed> $context_snapshot
 * @property Carbon $occurred_at
 */
#[Fillable(['workflow_instance_id', 'transition_name', 'from_state', 'to_state', 'actor_id', 'comment', 'rule_evaluation', 'context_snapshot', 'occurred_at'])]
class WorkflowTransition extends Model
{
    /** @use HasFactory<WorkflowTransitionFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['rule_evaluation' => 'array', 'context_snapshot' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
