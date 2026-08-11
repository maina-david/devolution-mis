<?php

namespace App\Actions;

use App\Models\KnowledgeItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionKnowledgeItem
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(KnowledgeItem $item, User $actor, array $attributes): KnowledgeItem
    {
        return DB::transaction(function () use ($item, $actor, $attributes): KnowledgeItem {
            $transition = (string) $attributes['transition'];
            $instance = $this->transitionWorkflow->handle($item->workflowInstance()->firstOrFail(), $transition, $actor, [], $attributes['rationale']);
            $item->update([
                'status' => $instance->current_state,
                'published_on' => $transition === 'publish' ? today() : $item->published_on,
                'review_due_at' => $transition === 'publish' ? now()->addYear() : $item->review_due_at,
            ]);
            $this->auditLogger->record($actor, $item, 'knowledge.item.transitioned', "Knowledge item {$item->reference} transitioned to {$instance->current_state}.", $item->county_id, ['transition' => $transition]);

            return $item->refresh();
        });
    }
}
