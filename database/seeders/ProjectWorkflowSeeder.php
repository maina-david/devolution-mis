<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class ProjectWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'PROJECT-LIFECYCLE'], ['name' => 'Devolution project lifecycle', 'module' => 'project-management', 'description' => 'Governed initiation, planning, execution and closure of devolution investments.', 'status' => 'active']);
        $publishedVersion = $definition->versions()->where('status', 'published')->first();
        $configuration = $publishedVersion?->configuration;
        $transitions = is_array($configuration) && isset($configuration['transitions']) && is_array($configuration['transitions']) ? $configuration['transitions'] : [];
        $hasClosureEvidenceRule = false;
        foreach ($transitions as $transition) {
            if (! is_array($transition) || ($transition['name'] ?? null) !== 'submit_closure' || ! isset($transition['rules']) || ! is_array($transition['rules'])) {
                continue;
            }
            foreach ($transition['rules'] as $rule) {
                if (is_array($rule) && ($rule['field'] ?? null) === 'closure_report_present') {
                    $hasClosureEvidenceRule = true;
                    break 2;
                }
            }
        }
        if ($hasClosureEvidenceRule) {
            return;
        }
        $version = $definition->versions()->create(['version' => ((int) $definition->versions()->max('version')) + 1, 'configuration' => [
            'initial_state' => 'initiation', 'states' => ['initiation', 'planning', 'execution', 'closure_review', 'closed'], 'terminal_states' => ['closed'],
            'state_slas' => ['initiation' => 120, 'planning' => 240, 'closure_review' => 120],
            'start_permission' => ProgrammePermission::ManageProjects->value, 'escalation_permission' => ProgrammePermission::ManageWorkflows->value,
            'transitions' => [
                ['name' => 'plan', 'from' => 'initiation', 'to' => 'planning', 'permission' => ProgrammePermission::ManageProjects->value],
                ['name' => 'start_execution', 'from' => 'planning', 'to' => 'execution', 'permission' => ProgrammePermission::ManageProjects->value],
                ['name' => 'submit_closure', 'from' => 'execution', 'to' => 'closure_review', 'permission' => ProgrammePermission::ManageProjects->value, 'rules' => [['field' => 'physical_progress', 'operator' => 'gte', 'value' => 100], ['field' => 'closure_report_present', 'operator' => 'eq', 'value' => true]]],
                ['name' => 'approve_closure', 'from' => 'closure_review', 'to' => 'closed', 'permission' => ProgrammePermission::VerifyProjectUpdates->value, 'separation_from' => ['submit_closure'], 'terminal' => true],
            ], 'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
