<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowRegistryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function configuration(string $finalState = 'approved'): array
    {
        return [
            'initial_state' => 'draft',
            'states' => ['draft', 'submitted', $finalState],
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted'],
                ['name' => 'approve', 'from' => 'submitted', 'to' => $finalState],
            ],
            'rules' => [['field' => 'evidence_count', 'operator' => 'gte', 'value' => 1]],
        ];
    }

    public function test_administrator_can_create_version_and_publish_a_valid_workflow(): void
    {
        $admin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($admin)->post(route('workflows.store', $admin->currentTeam->slug), [
            'code' => 'ACPA-ASSESSMENT',
            'name' => 'Annual County Performance Assessment',
            'module' => 'performance-assessment',
            'description' => 'Assessment evidence, review and approval lifecycle.',
            'status' => 'active',
        ])->assertRedirect();

        $workflow = WorkflowDefinition::query()->sole();
        $this->actingAs($admin)->post(route('workflows.versions.store', [$admin->currentTeam->slug, $workflow]), [
            'configuration' => $this->configuration(),
        ])->assertRedirect();

        $version = WorkflowVersion::query()->sole();
        $this->actingAs($admin)->patch(route('workflows.versions.publish', [$admin->currentTeam->slug, $workflow, $version]))->assertRedirect();

        $version->refresh();
        $this->assertTrue(Str::isUuid($workflow->id));
        $this->assertTrue(Str::isUuid($version->id));
        $this->assertSame('published', $version->status);
        $this->assertSame(64, Str::length((string) $version->checksum));
        $this->assertSame($admin->id, $version->published_by);
        $this->assertNotNull($version->published_at);
        $this->assertNotNull($version->effective_from);
        $this->assertSame(3, AuditEvent::query()->count());

        $this->actingAs($admin)->get(route('workflows.index', $admin->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workflows/index')
                ->where('workflows.data.0.code', 'ACPA-ASSESSMENT')
                ->where('workflows.data.0.versions.0.status', 'published'));
    }

    public function test_publishing_next_version_retires_previous_release(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workflow = WorkflowDefinition::factory()->create();
        $first = WorkflowVersion::factory()->create(['workflow_definition_id' => $workflow->id, 'configuration' => $this->configuration()]);

        $this->actingAs($admin)->patch(route('workflows.versions.publish', [$admin->currentTeam->slug, $workflow, $first]))->assertRedirect();
        $this->actingAs($admin)->post(route('workflows.versions.store', [$admin->currentTeam->slug, $workflow]), ['configuration' => $this->configuration('completed')])->assertRedirect();
        $second = WorkflowVersion::query()->where('version', 2)->sole();
        $this->actingAs($admin)->patch(route('workflows.versions.publish', [$admin->currentTeam->slug, $workflow, $second]))->assertRedirect();

        $this->assertSame('retired', $first->refresh()->status);
        $this->assertNotNull($first->effective_to);
        $this->assertSame('published', $second->refresh()->status);
    }

    public function test_invalid_state_references_are_rejected_and_county_user_is_forbidden(): void
    {
        $admin = User::factory()->devolutionAdmin()->create();
        $official = User::factory()->countyOfficial()->create();
        $workflow = WorkflowDefinition::factory()->create();
        $configuration = $this->configuration();
        $configuration['transitions'][0]['to'] = 'undeclared';

        $this->actingAs($admin)->post(route('workflows.versions.store', [$admin->currentTeam->slug, $workflow]), ['configuration' => $configuration])
            ->assertSessionHasErrors('configuration.transitions.0.to');
        $this->assertDatabaseCount('workflow_versions', 0);

        $this->actingAs($official)->get(route('workflows.index', $official->currentTeam->slug))->assertForbidden();
        $this->actingAs($official)->post(route('workflows.store', $official->currentTeam->slug), [])->assertForbidden();
    }

    public function test_database_prevents_released_versions_from_being_changed_or_deleted(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability trigger is database specific.');
        }

        $admin = User::factory()->platformAdmin()->create();
        $workflow = WorkflowDefinition::factory()->create();
        $version = WorkflowVersion::factory()->create(['workflow_definition_id' => $workflow->id, 'configuration' => $this->configuration()]);
        $this->actingAs($admin)->patch(route('workflows.versions.publish', [$admin->currentTeam->slug, $workflow, $version]))->assertRedirect();

        try {
            WorkflowVersion::query()->whereKey($version->id)->update(['configuration' => $this->configuration('changed')]);
            $this->fail('Published workflow version update should have been rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Published workflow versions may only be retired', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        WorkflowVersion::query()->whereKey($version->id)->delete();
    }
}
