<?php

namespace Tests\Feature;

use App\Actions\StartWorkflow;
use App\Actions\TransitionWorkflow;
use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowEscalation;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Notifications\ProgrammeAlert;
use App\Services\WorkflowSlaMonitor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
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
            'escalation_permission' => ProgrammePermission::ManageWorkflows->value,
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'submitted',
                    'permission' => ProgrammePermission::SubmitAssessment->value,
                    'rules' => [['field' => 'evidence_count', 'operator' => 'gte', 'value' => 1]],
                ],
                [
                    'name' => 'approve',
                    'from' => 'submitted',
                    'to' => 'approved',
                    'permission' => ProgrammePermission::ApproveAssessment->value,
                    'separation_from' => ['submit'],
                    'terminal' => true,
                ],
            ],
            'rules' => [],
        ];
    }

    /** @return array{WorkflowDefinition, WorkflowVersion, Assessment} */
    private function workflowFixture(): array
    {
        $definition = WorkflowDefinition::factory()->create(['code' => 'ACPA-RUNTIME']);
        $version = WorkflowVersion::factory()->published()->create([
            'workflow_definition_id' => $definition->id,
            'configuration' => $this->configuration(),
        ]);
        $assessment = Assessment::factory()->create();

        return [$definition, $version, $assessment];
    }

    public function test_published_workflow_executes_rules_permissions_sla_and_terminal_transition(): void
    {
        [$definition, $version, $assessment] = $this->workflowFixture();
        $submitter = User::factory()->countyAdmin($assessment->county)->create();
        $approver = User::factory()->topManagement()->create();

        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $submitter, ['evidence_count' => 1], $assessment->county_id);

        $this->assertTrue(Str::isUuid($instance->id));
        $this->assertSame($version->id, $instance->workflow_version_id);
        $this->assertSame('draft', $instance->current_state);
        $this->assertSame($assessment->id, $instance->subject_id);
        $this->assertSame(24, (int) $instance->started_at->diffInHours($instance->due_at));

        $instance = app(TransitionWorkflow::class)->handle($instance, 'submit', $submitter, [], 'County attestation complete.');
        $this->assertSame('submitted', $instance->current_state);
        $this->assertSame(48, (int) $instance->state_entered_at->diffInHours($instance->due_at));

        $instance = app(TransitionWorkflow::class)->handle($instance, 'approve', $approver, [], 'Independent approval complete.');
        $this->assertSame('completed', $instance->status);
        $this->assertSame('approved', $instance->current_state);
        $this->assertNotNull($instance->completed_at);
        $this->assertNull($instance->due_at);
        $this->assertSame(['start', 'submit', 'approve'], $instance->transitions()->orderBy('occurred_at')->pluck('transition_name')->all());
        $this->assertSame(3, AuditEvent::query()->count());
    }

    public function test_failed_rules_and_permissions_do_not_change_workflow_state(): void
    {
        [$definition, , $assessment] = $this->workflowFixture();
        $admin = User::factory()->countyAdmin($assessment->county)->create();
        $official = User::factory()->countyOfficial($assessment->county)->create();
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $admin, ['evidence_count' => 0], $assessment->county_id);

        try {
            app(TransitionWorkflow::class)->handle($instance, 'submit', $admin);
            $this->fail('A transition with failed rules should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transition', $exception->errors());
        }

        try {
            app(TransitionWorkflow::class)->handle($instance, 'submit', $official, ['evidence_count' => 1]);
            $this->fail('An actor without the configured permission should be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame('draft', $instance->refresh()->current_state);
        $this->assertSame(1, $instance->transitions()->count());
    }

    public function test_separation_of_duties_blocks_submitter_from_approving(): void
    {
        [$definition, , $assessment] = $this->workflowFixture();
        $actor = User::factory()->countyAdmin($assessment->county)->create();
        $actor->givePermissionTo(Permission::findOrCreate(ProgrammePermission::ApproveAssessment->value, 'web'));
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $actor, ['evidence_count' => 1], $assessment->county_id);
        $instance = app(TransitionWorkflow::class)->handle($instance, 'submit', $actor);

        $this->expectException(AuthorizationException::class);
        app(TransitionWorkflow::class)->handle($instance, 'approve', $actor);
    }

    public function test_sla_monitor_creates_one_escalation_per_overdue_state_and_completion_resolves_it(): void
    {
        [$definition, , $assessment] = $this->workflowFixture();
        $submitter = User::factory()->countyAdmin($assessment->county)->create();
        $approver = User::factory()->topManagement()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $submitter, ['evidence_count' => 1], $assessment->county_id);
        $instance->update(['due_at' => now()->subMinute()]);
        Notification::fake();

        $this->assertSame(1, app(WorkflowSlaMonitor::class)->escalateOverdue());
        $this->assertSame(0, app(WorkflowSlaMonitor::class)->escalateOverdue());
        $escalation = WorkflowEscalation::query()->sole();
        $this->assertSame('open', $escalation->status);
        $this->assertSame('draft', $escalation->metadata['state']);
        Notification::assertSentTo($administrator, ProgrammeAlert::class, fn (ProgrammeAlert $alert): bool => $alert->category === 'workflow_sla');

        $instance = app(TransitionWorkflow::class)->handle($instance, 'submit', $submitter);
        app(TransitionWorkflow::class)->handle($instance, 'approve', $approver);
        $this->assertSame('resolved', $escalation->refresh()->status);

        $this->artisan('workflows:escalate-overdue')->assertSuccessful()->expectsOutput('Created 0 workflow SLA escalation(s).');
    }

    public function test_workflow_transition_history_is_database_immutable(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability trigger is database specific.');
        }

        [$definition, , $assessment] = $this->workflowFixture();
        $actor = User::factory()->countyAdmin($assessment->county)->create();
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $actor, ['evidence_count' => 1], $assessment->county_id);
        $transition = WorkflowTransition::query()->sole();

        try {
            WorkflowTransition::query()->whereKey($transition->id)->update(['to_state' => 'tampered']);
            $this->fail('Workflow transition mutation should have been rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Workflow transition history is immutable', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        WorkflowTransition::query()->whereKey($transition->id)->delete();
    }

    public function test_runtime_records_follow_uuid_and_soft_delete_policy(): void
    {
        $instance = WorkflowInstance::factory()->create();
        $escalation = WorkflowEscalation::factory()->create(['workflow_instance_id' => $instance->id]);

        $this->assertTrue(Str::isUuid($instance->id));
        $this->assertTrue(Str::isUuid($escalation->id));
        $instance->delete();
        $escalation->delete();
        $this->assertSoftDeleted($instance);
        $this->assertSoftDeleted($escalation);
    }

    public function test_registry_reports_active_and_overdue_runtime_instances(): void
    {
        [$definition, , $assessment] = $this->workflowFixture();
        $submitter = User::factory()->countyAdmin($assessment->county)->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $submitter, ['evidence_count' => 1], $assessment->county_id);
        $instance->update(['due_at' => now()->subMinute()]);

        $this->actingAs($administrator)->get(route('workflows.index', $administrator->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workflows.data.0.activeInstances', 1)
                ->where('workflows.data.0.overdueInstances', 1));
    }
}
