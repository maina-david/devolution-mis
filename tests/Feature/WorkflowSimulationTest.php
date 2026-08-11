<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSimulationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'initial_state' => 'draft',
            'states' => ['draft', 'submitted', 'approved'],
            'terminal_states' => ['approved'],
            'state_slas' => ['draft' => 24, 'submitted' => 48],
            'start_permission' => ProgrammePermission::SubmitAssessment->value,
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'permission' => ProgrammePermission::SubmitAssessment->value, 'rules' => [['field' => 'evidence_count', 'operator' => 'gte', 'value' => 1]]],
                ['name' => 'approve', 'from' => 'submitted', 'to' => 'approved', 'permission' => ProgrammePermission::ApproveAssessment->value, 'separation_from' => ['submit'], 'terminal' => true],
            ],
            'rules' => [],
        ];
    }

    public function test_administrator_can_simulate_a_terminal_path_without_operational_side_effects(): void
    {
        $administrator = User::factory()->devolutionAdmin()->create();
        $submitter = User::factory()->countyAdmin()->create();
        $approver = User::factory()->topManagement()->create();
        $definition = WorkflowDefinition::factory()->create();
        $version = WorkflowVersion::factory()->create(['workflow_definition_id' => $definition->id, 'configuration' => $this->configuration()]);

        $response = $this->actingAs($administrator)->postJson(route('workflows.versions.simulate', [$administrator->currentTeam->slug, $definition, $version]), [
            'started_at' => '2026-08-10T08:00:00+03:00',
            'started_by' => $submitter->id,
            'initial_context' => ['evidence_count' => 0],
            'steps' => [
                ['transition_name' => 'submit', 'actor_id' => $submitter->id, 'context_changes' => ['evidence_count' => 1]],
                ['transition_name' => 'approve', 'actor_id' => $approver->id, 'context_changes' => []],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('simulation.passed', true)
            ->assertJsonPath('simulation.completed', true)
            ->assertJsonPath('simulation.finalState', 'approved')
            ->assertJsonPath('simulation.steps.0.ruleEvaluation.results.0.passed', true)
            ->assertJsonPath('simulation.steps.1.terminal', true)
            ->assertJsonStructure(['simulation' => ['scenarioChecksum', 'version' => ['checksum']]]);
        $this->assertSame(0, WorkflowInstance::query()->count());
        $this->assertSame(0, WorkflowTransition::query()->count());
        $this->assertSame(0, AuditEvent::query()->where('action', 'like', 'workflow.%')->count());
    }

    public function test_simulation_reports_permission_rule_and_separation_failures_as_evidence(): void
    {
        $administrator = User::factory()->devolutionAdmin()->create();
        $actor = User::factory()->countyAdmin()->create();
        $actor->givePermissionTo(Permission::findOrCreate(ProgrammePermission::ApproveAssessment->value, 'web'));
        $definition = WorkflowDefinition::factory()->create();
        $version = WorkflowVersion::factory()->create(['workflow_definition_id' => $definition->id, 'configuration' => $this->configuration()]);

        $base = ['started_at' => now()->toIso8601String(), 'started_by' => $actor->id, 'initial_context' => ['evidence_count' => 0]];
        $this->actingAs($administrator)->postJson(route('workflows.versions.simulate', [$administrator->currentTeam->slug, $definition, $version]), $base + ['steps' => [['transition_name' => 'submit', 'actor_id' => $actor->id, 'context_changes' => []]]])
            ->assertOk()->assertJsonPath('simulation.failureCode', 'rules_failed');

        $this->actingAs($administrator)->postJson(route('workflows.versions.simulate', [$administrator->currentTeam->slug, $definition, $version]), array_replace($base, ['initial_context' => ['evidence_count' => 1], 'steps' => [['transition_name' => 'submit', 'actor_id' => $actor->id, 'context_changes' => []], ['transition_name' => 'approve', 'actor_id' => $actor->id, 'context_changes' => []]]]))
            ->assertOk()->assertJsonPath('simulation.failureCode', 'separation_of_duties_failed');
    }

    public function test_simulation_is_authorized_validated_and_scoped_to_its_definition(): void
    {
        $administrator = User::factory()->devolutionAdmin()->create();
        $countyUser = User::factory()->countyOfficial()->create();
        $definition = WorkflowDefinition::factory()->create();
        $otherDefinition = WorkflowDefinition::factory()->create();
        $version = WorkflowVersion::factory()->create(['workflow_definition_id' => $definition->id, 'configuration' => $this->configuration()]);
        $payload = ['started_at' => now()->toIso8601String(), 'started_by' => $administrator->id, 'initial_context' => [], 'steps' => []];

        $this->actingAs($countyUser)->postJson(route('workflows.versions.simulate', [$countyUser->currentTeam->slug, $definition, $version]), $payload)->assertForbidden();
        $this->actingAs($administrator)->postJson(route('workflows.versions.simulate', [$administrator->currentTeam->slug, $otherDefinition, $version]), $payload)->assertNotFound();
        $this->actingAs($administrator)->postJson(route('workflows.versions.simulate', [$administrator->currentTeam->slug, $definition, $version]), ['started_at' => 'invalid'])->assertJsonValidationErrors(['started_at', 'started_by', 'initial_context', 'steps']);
    }
}
