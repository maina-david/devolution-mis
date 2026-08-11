<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\AuditEvent;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Database\Seeders\CitizenCaseWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkWorkspaceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_atomically_deactivate_selected_users_with_audit_evidence(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $targets = User::factory()->count(2)->create();

        $this->actingAs($admin)->delete(route('programme-users.bulk-destroy', $admin->currentTeam->slug), [
            'ids' => $targets->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($targets as $target) {
            $this->assertSoftDeleted($target);
        }

        $this->assertSame(2, AuditEvent::query()->where('action', 'access.deactivated')->count());
    }

    public function test_county_admin_can_atomically_submit_selected_assessments(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessments = Assessment::factory()->count(2)
            ->sequence(['cycle' => '2025/26 ACPA'], ['cycle' => '2026/27 ACPA'])
            ->create([
                'county_id' => $county->id,
                'status' => AssessmentStatus::EvidenceCollection,
            ]);

        $this->actingAs($admin)->patch(route('assessments.bulk-transition', $admin->currentTeam->slug), [
            'ids' => $assessments->pluck('id')->all(),
            'transition' => 'submit',
        ])->assertRedirect();

        $this->assertSame(2, Assessment::query()->whereKey($assessments->pluck('id'))->where('status', AssessmentStatus::Submitted)->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'assessment.submitted')->count());
    }

    public function test_bulk_assessment_submission_rejects_mixed_state_and_scope_without_partial_changes(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $draft = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2025/26 ACPA', 'status' => AssessmentStatus::Draft]);
        $submitted = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2026/27 ACPA', 'status' => AssessmentStatus::Submitted]);
        $outside = Assessment::factory()->create(['county_id' => $otherCounty->id, 'status' => AssessmentStatus::Draft]);

        $this->actingAs($admin)->patch(route('assessments.bulk-transition', $admin->currentTeam->slug), [
            'ids' => [$draft->id, $submitted->id],
            'transition' => 'submit',
        ])->assertStatus(409);
        $this->assertSame(AssessmentStatus::Draft, $draft->fresh()->status);

        $this->actingAs($admin)->patch(route('assessments.bulk-transition', $admin->currentTeam->slug), [
            'ids' => [$draft->id, $outside->id],
            'transition' => 'submit',
        ])->assertForbidden();
        $this->assertSame(AssessmentStatus::Draft, $draft->fresh()->status);
        $this->assertDatabaseMissing('audit_events', ['action' => 'assessment.submitted']);
    }

    public function test_assessor_can_atomically_start_reviews_for_an_assigned_county_portfolio(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $assessments = Assessment::factory()->count(2)
            ->sequence(['cycle' => '2025/26 ACPA'], ['cycle' => '2026/27 ACPA'])
            ->create([
                'county_id' => $county->id,
                'status' => AssessmentStatus::Submitted,
            ]);

        $this->actingAs($assessor)->patch(route('assessments.bulk-transition', $assessor->currentTeam->slug), [
            'ids' => $assessments->pluck('id')->all(),
            'transition' => 'review',
        ])->assertRedirect();

        $this->assertSame(2, Assessment::query()->whereKey($assessments->pluck('id'))->where('status', AssessmentStatus::UnderAssessment)->where('assessor_id', $assessor->id)->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'assessment.under_assessment')->count());
    }

    public function test_bulk_user_deactivation_rolls_back_when_selection_contains_an_unauthorized_identity(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->countyAdmin($home)->create();
        $allowed = User::factory()->countyOfficial($home)->create();
        $forbidden = User::factory()->countyOfficial($other)->create();

        $this->actingAs($admin)->delete(route('programme-users.bulk-destroy', $admin->currentTeam->slug), [
            'ids' => [$allowed->id, $forbidden->id],
        ])->assertForbidden();

        $this->assertNotSoftDeleted($allowed);
        $this->assertNotSoftDeleted($forbidden);
        $this->assertDatabaseMissing('audit_events', ['action' => 'access.deactivated']);
    }

    public function test_assessor_can_atomically_verify_clean_evidence_in_their_county_portfolio(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $documents = AssessmentDocument::factory()->count(2)->create([
            'assessment_id' => $assessment->id,
            'county_id' => $county->id,
            'scan_status' => 'clean',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($assessor)->patch(route('evidence.bulk-verification', $assessor->currentTeam->slug), [
            'ids' => $documents->pluck('id')->all(),
            'status' => 'verified',
        ])->assertRedirect();

        $this->assertSame(2, AssessmentDocument::query()->whereIn('id', $documents->pluck('id'))->where('verification_status', 'verified')->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'evidence.verified')->count());
    }

    public function test_bulk_evidence_verification_rejects_mixed_scope_and_quarantined_selections_without_partial_changes(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $other = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $allowedAssessment = Assessment::factory()->create(['county_id' => $county->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $other->id]);
        $allowed = AssessmentDocument::factory()->create(['assessment_id' => $allowedAssessment->id, 'county_id' => $county->id, 'scan_status' => 'clean', 'verification_status' => 'pending']);
        $forbidden = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $other->id, 'scan_status' => 'clean', 'verification_status' => 'pending']);

        $this->actingAs($assessor)->patch(route('evidence.bulk-verification', $assessor->currentTeam->slug), [
            'ids' => [$allowed->id, $forbidden->id],
            'status' => 'verified',
        ])->assertForbidden();

        $this->assertSame('pending', $allowed->fresh()->verification_status);

        $quarantined = AssessmentDocument::factory()->create(['assessment_id' => $allowedAssessment->id, 'county_id' => $county->id, 'scan_status' => 'quarantined', 'verification_status' => 'pending']);
        $this->actingAs($assessor)->patch(route('evidence.bulk-verification', $assessor->currentTeam->slug), [
            'ids' => [$allowed->id, $quarantined->id],
            'status' => 'verified',
        ])->assertStatus(409);

        $this->assertSame('pending', $allowed->fresh()->verification_status);
        $this->assertDatabaseMissing('audit_events', ['action' => 'evidence.verified']);
    }

    public function test_bulk_requests_validate_bounded_distinct_uuid_selections(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->delete(route('programme-users.bulk-destroy', $admin->currentTeam->slug), [
            'ids' => ['not-a-uuid', 'not-a-uuid'],
        ])->assertSessionHasErrors(['ids.0', 'ids.1']);
    }

    public function test_county_manager_can_atomically_triage_and_assign_selected_citizen_cases(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $manager = User::factory()->countyAdmin($county)->create();
        $handler = User::factory()->countyOfficial($county)->create();
        User::factory()->devolutionAdmin()->create();
        $release = $this->publishedReferenceRelease([$county], $manager);
        $this->seed(CitizenCaseWorkflowSeeder::class);
        $cases = CitizenCase::factory()->count(2)->create(['county_id' => $county->id]);

        $this->actingAs($manager)->patch(route('citizen-cases.bulk-triage', $manager->currentTeam->slug), [
            'ids' => $cases->pluck('id')->all(),
            'assigned_to' => $handler->id,
            'priority' => 'high',
            'is_sensitive' => false,
            'triage_note' => 'Jurisdiction and common routing requirements verified for the complete selection.',
        ])->assertRedirect();

        $this->assertSame(2, CitizenCase::query()->whereKey($cases->pluck('id'))->where('status', 'triaged')->where('assigned_to', $handler->id)->count());
        $this->assertSame(2, CitizenCase::query()->whereKey($cases->pluck('id'))->where('triage_reference_data_release_id', $release->id)->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'citizen_case.triaged')->count());
    }

    public function test_bulk_citizen_case_triage_rejects_mixed_state_and_county_scope_without_partial_changes(): void
    {
        Notification::fake();
        $home = County::factory()->create();
        $other = County::factory()->create();
        $manager = User::factory()->countyAdmin($home)->create();
        $handler = User::factory()->countyOfficial($home)->create();
        User::factory()->devolutionAdmin()->create();
        $this->seed(CitizenCaseWorkflowSeeder::class);
        $received = CitizenCase::factory()->create(['county_id' => $home->id]);
        $alreadyTriaged = CitizenCase::factory()->create(['county_id' => $home->id, 'status' => 'triaged']);
        $outside = CitizenCase::factory()->create(['county_id' => $other->id]);
        $payload = [
            'assigned_to' => $handler->id,
            'priority' => 'medium',
            'is_sensitive' => false,
            'triage_note' => 'Common routing was reviewed before attempting the governed bulk assignment.',
        ];

        $this->actingAs($manager)->patch(route('citizen-cases.bulk-triage', $manager->currentTeam->slug), [
            ...$payload,
            'ids' => [$received->id, $alreadyTriaged->id],
        ])->assertStatus(409);
        $this->assertSame('received', $received->fresh()->status);

        $this->actingAs($manager)->patch(route('citizen-cases.bulk-triage', $manager->currentTeam->slug), [
            ...$payload,
            'ids' => [$received->id, $outside->id],
        ])->assertForbidden();
        $this->assertSame('received', $received->fresh()->status);
        $this->assertDatabaseMissing('audit_events', ['action' => 'citizen_case.triaged']);
    }

    public function test_filtered_bulk_citizen_case_triage_applies_to_all_matching_pages_only(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $manager = User::factory()->countyAdmin($county)->create();
        $handler = User::factory()->countyOfficial($county)->create();
        User::factory()->devolutionAdmin()->create();
        $release = $this->publishedReferenceRelease([$county], $manager);
        $this->seed(CitizenCaseWorkflowSeeder::class);
        $matching = CitizenCase::factory()->count(18)->create([
            'county_id' => $county->id,
            'category' => 'water-service-filtered-bulk',
        ]);
        $outsideFilter = CitizenCase::factory()->create([
            'county_id' => $county->id,
            'category' => 'road-maintenance',
        ]);

        $this->actingAs($manager)->patch(route('citizen-cases.bulk-triage', $manager->currentTeam->slug), [
            'selection_mode' => 'filtered',
            'search' => 'water-service-filtered-bulk',
            'assigned_to' => $handler->id,
            'priority' => 'high',
            'is_sensitive' => false,
            'triage_note' => 'The active filtered queue was reviewed and assigned as one governed workload.',
        ])->assertRedirect();

        $this->assertSame(18, CitizenCase::query()->whereKey($matching->pluck('id'))->where('status', 'triaged')->count());
        $this->assertSame(18, CitizenCase::query()->whereKey($matching->pluck('id'))->where('triage_reference_data_release_id', $release->id)->count());
        $this->assertSame('received', $outsideFilter->fresh()->status);
        $this->assertSame(18, AuditEvent::query()->where('action', 'citizen_case.triaged')->count());
    }

    public function test_dedicated_workspace_registers_expose_governed_selected_exports(): void
    {
        $component = file_get_contents(resource_path('js/components/workspace-data-table.tsx'));
        $this->assertIsString($component);
        $this->assertStringContainsString('const hasBulkActions = Boolean(renderBulkActions || bulkExport)', $component);
        $this->assertStringContainsString('<WorkspaceBulkExportActions', $component);

        $expectedWorkspaces = [
            'projects' => 'js/pages/projects/index.tsx',
            'partners' => 'js/pages/partners/index.tsx',
            'dswg' => 'js/pages/dswg/index.tsx',
            'igr-resolutions' => 'js/pages/igr-resolutions/index.tsx',
            'igr-gaps' => 'js/pages/igr-resolutions/index.tsx',
            'citizen-cases' => 'js/pages/citizen-cases/index.tsx',
            'monitoring-evaluation' => 'js/pages/monitoring-evaluation/index.tsx',
            'travel-clearance' => 'js/pages/travel-clearance/index.tsx',
            'departmental-performance' => 'js/pages/departmental-performance/index.tsx',
            'learning' => 'js/pages/learning/index.tsx',
            'knowledge' => 'js/pages/knowledge/index.tsx',
            'knowledge-innovations' => 'js/pages/knowledge/index.tsx',
            'knowledge-moderation' => 'js/pages/knowledge/index.tsx',
            'integrations' => 'js/pages/integrations/index.tsx',
            'operations' => 'js/pages/operations/index.tsx',
            'data-governance' => 'js/pages/data-governance/index.tsx',
            'privacy-incidents' => 'js/pages/data-governance/index.tsx',
            'security-incidents' => 'js/pages/security-governance/index.tsx',
            'access-delegations' => 'js/pages/security-governance/index.tsx',
            'security-governance' => 'js/pages/security-governance/index.tsx',
        ];

        foreach ($expectedWorkspaces as $workspace => $path) {
            $source = file_get_contents(resource_path($path));
            $this->assertIsString($source);
            $this->assertMatchesRegularExpression(
                "/bulkExport=\\{\\{[\\s\\S]{0,180}workspace: '{$workspace}'/",
                $source,
                "{$workspace} must expose selected exports.",
            );
        }
    }

    /**
     * @param  list<County>  $counties
     */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];

        return ReferenceDataRelease::factory()->create([
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
