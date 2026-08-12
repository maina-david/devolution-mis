<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\Permission;
use App\Models\ReferenceDataRelease;
use App\Models\UatAcceptance;
use App\Models\UatCampaign;
use App\Models\UatExecution;
use App\Models\UatFinding;
use App\Models\UatScenario;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_pilot_execution_findings_and_acceptance_are_governed_end_to_end(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $tester = User::factory()->countyOfficial($county)->create();
        $findingOwner = User::factory()->countyAdmin($county)->create();
        $approver = User::factory()->topManagement()->create();
        $approver->assignedCounties()->attach($county);
        $release = $this->publishedReferenceRelease([$county], $administrator);

        $this->actingAs($administrator)
            ->post(route('change-readiness.uat.campaigns.store'), $this->campaignPayload([$county->id]))
            ->assertRedirect();

        $campaign = UatCampaign::query()->sole();
        $this->assertTrue(Str::isUuid($campaign->id));
        $this->assertSame($release->id, $campaign->reference_data_release_id);
        $this->assertSame('planning', $campaign->status);

        $this->actingAs($administrator)
            ->post(route('change-readiness.uat.scenarios.store', $campaign), $this->scenarioPayload())
            ->assertRedirect();

        $scenario = UatScenario::query()->sole();
        $this->actingAs($findingOwner)
            ->post(route('change-readiness.uat.executions.store', $scenario), $this->executionPayload($county, 'pass'))
            ->assertSessionHasErrors('outcome');

        $this->actingAs($tester)
            ->post(route('change-readiness.uat.executions.store', $scenario), $this->executionPayload($county, 'fail', $findingOwner))
            ->assertRedirect();

        $failedExecution = UatExecution::query()->sole();
        $finding = UatFinding::query()->sole();
        $this->assertSame('executing', $campaign->refresh()->status);
        $this->assertSame('open', $finding->status);
        $this->assertSame(64, strlen($failedExecution->checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $failedExecution->id, 'action' => 'change-readiness.uat.execution.recorded']);

        $this->actingAs($tester)
            ->patch(route('change-readiness.uat.findings.update', $finding), ['action' => 'resolve', 'resolution' => 'The reporter cannot resolve a finding raised by the same identity.'])
            ->assertSessionHasErrors('action');
        $this->actingAs($findingOwner)
            ->patch(route('change-readiness.uat.findings.update', $finding), ['action' => 'resolve', 'resolution' => 'Corrected the authorization boundary and attached the retest evidence reference.'])
            ->assertRedirect();
        $this->actingAs($approver)
            ->patch(route('change-readiness.uat.findings.update', $finding), ['action' => 'verify', 'resolution' => 'Independently verified the corrective action against the governed scenario.'])
            ->assertRedirect();
        $this->assertSame('verified', $finding->refresh()->status);

        $this->actingAs($tester)
            ->post(route('change-readiness.uat.executions.store', $scenario), $this->executionPayload($county, 'pass'))
            ->assertRedirect();
        $this->actingAs($administrator)
            ->post(route('change-readiness.uat.campaigns.submit', $campaign), ['criteria_confirmed' => '1'])
            ->assertRedirect();

        $acceptance = UatAcceptance::query()->sole();
        $this->assertSame('pending', $acceptance->decision);
        $this->assertSame(1, $acceptance->coverage_snapshot['required_pairs']);
        $this->assertSame(1, $acceptance->coverage_snapshot['passing_pairs']);
        $this->assertSame('review', $campaign->refresh()->status);

        $this->actingAs($administrator)
            ->patch(route('change-readiness.uat.acceptances.update', $acceptance), ['decision' => 'accepted', 'decision_reason' => 'The campaign author cannot approve the acceptance evidence they submitted.'])
            ->assertForbidden();
        $this->actingAs($approver)
            ->patch(route('change-readiness.uat.acceptances.update', $acceptance), ['decision' => 'accepted', 'decision_reason' => 'All representative execution pairs passed and every finding was independently verified.'])
            ->assertRedirect();

        $this->assertSame('accepted', $campaign->refresh()->status);
        $this->assertSame($approver->id, $acceptance->refresh()->decided_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $acceptance->id, 'action' => 'change-readiness.uat.acceptance.accepted']);

        $this->expectException(QueryException::class);
        $acceptance->update(['decision_reason' => 'Terminal acceptance evidence cannot be rewritten.']);
    }

    public function test_uat_campaigns_and_execution_are_restricted_to_authorized_counties(): void
    {
        $county = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $tester = User::factory()->countyOfficial($county)->create();
        $outsideTester = User::factory()->countyOfficial($outsideCounty)->create();
        $this->publishedReferenceRelease([$county, $outsideCounty], $administrator);

        $this->actingAs($administrator)->post(route('change-readiness.uat.campaigns.store'), $this->campaignPayload([$county->id]))->assertRedirect();
        $campaign = UatCampaign::query()->sole();
        $this->actingAs($administrator)->post(route('change-readiness.uat.scenarios.store', $campaign), $this->scenarioPayload())->assertRedirect();
        $scenario = UatScenario::query()->sole();

        $this->actingAs($tester)->get(route('change-readiness.index'))->assertOk()->assertInertia(fn ($page) => $page->where('uatCampaigns.total', 1));
        $this->actingAs($tester)->getJson(route('search.global', ['q' => 'UAT-PILOT-2026']))->assertOk()->assertJsonFragment(['category' => 'Pilot UAT', 'id' => $campaign->id]);
        $this->actingAs($outsideTester)->get(route('change-readiness.index'))->assertOk()->assertInertia(fn ($page) => $page->where('uatCampaigns.total', 0));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($tester)->get(route('workspace.export', ['uat-campaigns', $format]))->assertOk()->assertDownload();
        }
        $this->actingAs($outsideTester)->post(route('change-readiness.uat.executions.store', $scenario), $this->executionPayload($outsideCounty, 'pass'))->assertForbidden();
        $this->assertDatabaseCount('uat_executions', 0);
    }

    public function test_campaign_planning_fails_closed_without_governed_county_catalogue_lineage(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();

        $this->actingAs($administrator)->post(route('change-readiness.uat.campaigns.store'), $this->campaignPayload([$county->id]))->assertStatus(409);
        $this->assertDatabaseCount('uat_campaigns', 0);

        $this->publishedReferenceRelease([], $administrator);
        $this->actingAs($administrator)->post(route('change-readiness.uat.campaigns.store'), $this->campaignPayload([$county->id]))->assertSessionHasErrors('county_ids');
        $this->assertDatabaseCount('uat_campaigns', 0);
    }

    public function test_uat_execution_permission_uses_uuid_role_and_permission_relationships(): void
    {
        $countyOfficial = User::factory()->countyOfficial()->create();
        $permission = Permission::query()->where('name', 'uat-evidence:record')->firstOrFail();

        $this->assertTrue(Str::isUuid($permission->id));
        $this->assertSame(['county-official'], $permission->roles()->pluck('name')->all());
        $this->assertTrue($countyOfficial->can('uat-evidence:record'));
    }

    public function test_uat_execution_evidence_cannot_be_rewritten_in_postgresql(): void
    {
        $execution = UatExecution::factory()->create();

        $this->expectException(QueryException::class);
        $execution->update(['actual_result' => 'An attempt to rewrite retained pilot execution evidence.']);
    }

    public function test_uat_finding_evidence_cannot_be_deleted_in_postgresql(): void
    {
        $finding = UatFinding::factory()->create();

        $this->expectException(QueryException::class);
        $finding->delete();
    }

    /** @param list<string> $countyIds */
    private function campaignPayload(array $countyIds): array
    {
        return [
            'code' => 'UAT-PILOT-2026',
            'name' => 'Representative county pilot acceptance',
            'objective' => 'Validate representative end-to-end county journeys and independent acceptance before phased production rollout.',
            'environment' => 'government-hosting-uat',
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'county_ids' => $countyIds,
            'acceptance_criteria' => ['Every required scenario and county pair has a passing latest execution.', 'Every finding is independently verified before submission.'],
            'required_roles' => ['county-official'],
            'minimum_counties' => count($countyIds),
        ];
    }

    /** @return array<string, mixed> */
    private function scenarioPayload(): array
    {
        return [
            'code' => 'ACPA-EVIDENCE-SUBMISSION',
            'module' => 'devolution-assessment',
            'title' => 'Submit a county assessment evidence package',
            'actor_role' => 'county-official',
            'priority' => 'critical',
            'journey' => 'The county official completes and submits an evidence-backed assessment for independent review.',
            'preconditions' => ['A published assessment cycle is assigned.', 'The official has county-scoped access.'],
            'steps' => ['Open the assigned assessment.', 'Upload evidence and attest completeness.', 'Submit for independent verification.'],
            'expected_result' => 'The submission is accepted once with immutable audit and evidence lineage.',
            'accessibility_needs' => 'Complete with keyboard-only navigation and announced errors.',
            'low_connectivity_variant' => 'Repeat under constrained bandwidth and verify recoverable uploads.',
            'required' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function executionPayload(County $county, string $outcome, ?User $findingOwner = null): array
    {
        $payload = [
            'county_id' => $county->id,
            'environment' => 'government-hosting-uat',
            'outcome' => $outcome,
            'actual_result' => $outcome === 'pass' ? 'The complete journey matched the governed expected result and retained the required evidence lineage.' : 'The journey failed at the independent evidence handoff and requires a corrective action.',
            'evidence_references' => ['DMS-UAT-2026-001'],
            'started_at' => now()->subHour()->toIso8601String(),
            'completed_at' => now()->subMinutes(30)->toIso8601String(),
        ];

        if ($outcome !== 'pass' && $findingOwner !== null) {
            $payload += [
                'finding_owner_id' => $findingOwner->id,
                'finding_severity' => 'high',
                'finding_title' => 'Independent evidence handoff failed',
                'finding_description' => 'The submitted evidence was not presented to the independent reviewer in the expected workflow state.',
                'finding_due_on' => now()->addWeek()->toDateString(),
            ];
        }

        return $payload;
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = ['counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id, 'code' => $county->code, 'name' => $county->name])->values()->all(), 'organizations' => [], 'sectors' => [], 'programmes' => []];

        return ReferenceDataRelease::factory()->create(['approved_by' => $approver->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
    }
}
