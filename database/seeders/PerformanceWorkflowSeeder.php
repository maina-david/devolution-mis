<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class PerformanceWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'DEPARTMENTAL-PERFORMANCE-LIFECYCLE'], ['name' => 'SDD departmental performance lifecycle', 'module' => 'departmental-performance', 'description' => 'Weighted goal agreement, employee self-review and independent supervisor final review.', 'status' => 'active']);
        $publishedVersion = $definition->versions()->where('status', 'published')->first();
        $configuration = $publishedVersion?->configuration;
        $transitions = is_array($configuration) && isset($configuration['transitions']) && is_array($configuration['transitions']) ? $configuration['transitions'] : [];
        $hasEvidenceRules = false;
        foreach ($transitions as $transition) {
            if (! is_array($transition) || ! isset($transition['rules']) || ! is_array($transition['rules'])) {
                continue;
            }
            foreach ($transition['rules'] as $rule) {
                if (is_array($rule) && ($rule['field'] ?? null) === 'goal_plan_evidence_present') {
                    $hasEvidenceRules = true;
                    break 2;
                }
            }
        }
        if ($hasEvidenceRules) {
            return;
        }
        $version = $definition->versions()->create(['version' => ((int) $definition->versions()->max('version')) + 1, 'configuration' => [
            'initial_state' => 'draft', 'states' => ['draft', 'goal_review', 'active', 'self_review', 'supervisor_review', 'finalized'], 'terminal_states' => ['finalized'],
            'state_slas' => ['goal_review' => 72, 'self_review' => 120, 'supervisor_review' => 72], 'start_permission' => ProgrammePermission::SubmitPerformancePlans->value,
            'transitions' => [
                ['name' => 'submit_goals', 'from' => 'draft', 'to' => 'goal_review', 'permission' => ProgrammePermission::SubmitPerformancePlans->value, 'rules' => [['field' => 'goal_weight_total', 'operator' => 'eq', 'value' => 100], ['field' => 'goal_plan_evidence_present', 'operator' => 'eq', 'value' => true]]],
                ['name' => 'approve_goals', 'from' => 'goal_review', 'to' => 'active', 'permission' => ProgrammePermission::ReviewPerformancePlans->value, 'separation_from' => ['submit_goals']],
                ['name' => 'return_goals', 'from' => 'goal_review', 'to' => 'draft', 'permission' => ProgrammePermission::ReviewPerformancePlans->value, 'separation_from' => ['submit_goals']],
                ['name' => 'start_review', 'from' => 'active', 'to' => 'self_review', 'permission' => ProgrammePermission::SubmitPerformancePlans->value],
                ['name' => 'submit_self_review', 'from' => 'self_review', 'to' => 'supervisor_review', 'permission' => ProgrammePermission::SubmitPerformancePlans->value, 'rules' => [['field' => 'self_review_complete', 'operator' => 'eq', 'value' => true], ['field' => 'self_review_evidence_present', 'operator' => 'eq', 'value' => true]]],
                ['name' => 'finalize_review', 'from' => 'supervisor_review', 'to' => 'finalized', 'permission' => ProgrammePermission::ReviewPerformancePlans->value, 'separation_from' => ['submit_goals', 'submit_self_review'], 'rules' => [['field' => 'supervisor_review_complete', 'operator' => 'eq', 'value' => true], ['field' => 'final_appraisal_evidence_present', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
            ], 'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
