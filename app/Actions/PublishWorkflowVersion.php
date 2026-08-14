<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class PublishWorkflowVersion
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    public function handle(WorkflowVersion $workflowVersion, User $publisher): WorkflowVersion
    {
        return DB::transaction(function () use ($workflowVersion, $publisher): WorkflowVersion {
            $definition = WorkflowDefinition::query()->lockForUpdate()->findOrFail($workflowVersion->workflow_definition_id);
            $workflowVersion->refresh();

            abort_unless($workflowVersion->status === 'draft', 409, __('workflow-management.errors.draft_publish_required'));

            $publishedAt = now();
            $definition->versions()->where('status', 'published')->update([
                'status' => 'retired',
                'effective_to' => $publishedAt,
            ]);

            $workflowVersion->update([
                'status' => 'published',
                'checksum' => $this->canonicalJson->checksum($workflowVersion->configuration),
                'effective_from' => $workflowVersion->effective_from ?? $publishedAt,
                'published_by' => $publisher->id,
                'published_at' => $publishedAt,
            ]);

            return $workflowVersion->refresh();
        }, attempts: 3);
    }
}
