<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\InnovationExperimentMilestone;
use App\Models\InnovationFundingDecision;
use App\Models\InnovationPanelReview;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Database\Seeders\KnowledgeWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InnovationPortfolioGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_independent_checksum_bound_panel_reviews_gate_incubation(): void
    {
        [$county, $innovation, $submitter, $firstReviewer, $secondReviewer] = $this->screeningInnovation();

        $this->actingAs($submitter)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload())->assertForbidden();
        $this->actingAs($firstReviewer)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload())->assertRedirect();
        $this->actingAs($firstReviewer)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload())->assertSessionHasErrors('reviewer');
        $this->actingAs($firstReviewer)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'accept_incubation', 'rationale' => 'A single opinion cannot authorize incubation.'])->assertSessionHasErrors('transition');
        $this->actingAs($secondReviewer)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload(['strategic_fit_score' => 80, 'feasibility_score' => 76]))->assertRedirect();

        $reviews = InnovationPanelReview::query()->orderBy('reviewed_at')->get();
        $this->assertCount(2, $reviews);
        $this->assertTrue(Str::isUuid($reviews->first()->id));
        $this->assertSame('IDMIS-INNOVATION-RUBRIC-v1', $reviews->first()->rubric_code);
        $this->assertSame(64, strlen($reviews->first()->rubric_checksum));
        $this->assertSame(64, strlen($reviews->first()->evidence_checksum));
        $this->assertSame('82.25', $reviews->first()->weighted_score);

        $this->actingAs($firstReviewer)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'accept_incubation', 'rationale' => 'Two independent panel opinions exceed the governed threshold.', 'incubation_support' => 'Controlled funding and pilot protocol.'])->assertRedirect();
        $this->assertSame('incubating', $innovation->refresh()->status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $reviews->first()->id, 'action' => 'knowledge.innovation.panel-reviewed', 'county_id' => $county->id]);
    }

    public function test_versioned_funding_and_defined_experiment_milestones_gate_pilot_launch(): void
    {
        [$county, $innovation, $submitter, $firstReviewer, $secondReviewer] = $this->incubatingInnovation();
        $manager = User::factory()->devolutionAdmin()->create();

        $invalidFunding = $this->fundingPayload(['amount' => 0]);
        $this->actingAs($manager)->post(route('knowledge.innovations.funding-decisions.store', [$innovation]), $invalidFunding)->assertSessionHasErrors('amount');
        $this->actingAs($firstReviewer)->post(route('knowledge.innovations.funding-decisions.store', [$innovation]), $this->fundingPayload())->assertForbidden();
        $this->actingAs($manager)->post(route('knowledge.innovations.funding-decisions.store', [$innovation]), $this->fundingPayload())->assertRedirect();
        $firstDecision = InnovationFundingDecision::query()->sole();
        $this->assertSame(1, $firstDecision->decision_version);
        $this->assertSame(64, strlen($firstDecision->evidence_checksum));

        $this->actingAs($manager)->post(route('knowledge.innovations.funding-decisions.store', [$innovation]), $this->fundingPayload(['decision_reference' => 'IFD-2026-002', 'amount' => 1750000]))->assertRedirect();
        $secondDecision = InnovationFundingDecision::query()->where('decision_version', 2)->sole();
        $this->assertSame($firstDecision->evidence_checksum, $secondDecision->previous_checksum);
        $this->actingAs($manager)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'start_pilot', 'rationale' => 'Funding alone is insufficient without measurable milestones.'])->assertSessionHasErrors('transition');

        $this->actingAs($manager)->post(route('knowledge.innovations.milestones.store', [$innovation]), $this->milestonePayload($submitter))->assertRedirect();
        $milestone = InnovationExperimentMilestone::query()->sole();
        $this->assertTrue(Str::isUuid($milestone->id));
        $this->assertSame('planned', $milestone->status);
        $this->actingAs($manager)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'start_pilot', 'rationale' => 'Current funding approval and measurable experiment protocol are recorded.', 'evidence_reference' => 'PILOT-PROTOCOL-2026-001'])->assertRedirect();
        $this->assertSame('piloting', $innovation->refresh()->status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $secondDecision->id, 'action' => 'knowledge.innovation.funding-decided', 'county_id' => $county->id]);
    }

    public function test_clean_county_evidence_and_independent_verification_gate_scale_up(): void
    {
        [$county, $innovation, $owner, $curator, $manager, $milestone] = $this->pilotingInnovation();
        $otherCounty = County::factory()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $cleanEvidence = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'scan_status' => 'clean', 'record_status' => 'active']);
        $otherEvidence = AssessmentDocument::factory()->create(['county_id' => $otherCounty->id, 'scan_status' => 'clean', 'record_status' => 'active']);

        $this->actingAs($owner)->patch(route('knowledge.innovations.milestones.update', [$innovation, $milestone]), ['status' => 'completed', 'actual_value' => '82%', 'outcome_summary' => 'The controlled pilot exceeded the target with complete signed evidence.', 'assessment_document_id' => $cleanEvidence->id])->assertSessionHasErrors('status');
        $this->actingAs($owner)->patch(route('knowledge.innovations.milestones.update', [$innovation, $milestone]), ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($owner)->patch(route('knowledge.innovations.milestones.update', [$innovation, $milestone]), ['status' => 'completed', 'actual_value' => '82%', 'outcome_summary' => 'The controlled pilot exceeded the target with complete signed evidence.', 'assessment_document_id' => $otherEvidence->id])->assertSessionHasErrors('assessment_document_id');
        $this->actingAs($owner)->patch(route('knowledge.innovations.milestones.update', [$innovation, $milestone]), ['status' => 'completed', 'actual_value' => '82%', 'outcome_summary' => 'The controlled pilot exceeded the target with complete signed evidence.', 'assessment_document_id' => $cleanEvidence->id])->assertRedirect();
        $this->actingAs($curator)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'scale', 'rationale' => 'Unverified outcome evidence cannot authorize scale.'])->assertSessionHasErrors('transition');
        $this->actingAs($owner)->patch(route('knowledge.innovations.milestones.verify', [$innovation, $milestone]), ['verification_decision' => 'verified', 'verification_rationale' => 'Attempted self-verification of pilot evidence.'])->assertForbidden();
        $this->actingAs($curator)->patch(route('knowledge.innovations.milestones.verify', [$innovation, $milestone]), ['verification_decision' => 'verified', 'verification_rationale' => 'Source file, metric calculation and county provenance independently verified.'])->assertRedirect();
        $this->actingAs($curator)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'scale', 'rationale' => 'All experiment outcomes have independently verified clean evidence.', 'evidence_reference' => 'PILOT-CLOSE-2026-001'])->assertRedirect();

        $this->assertSame('scaling', $innovation->refresh()->status);
        $this->assertSame('verified', $milestone->refresh()->verification_decision);
        $this->assertSame($cleanEvidence->id, $milestone->assessment_document_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $milestone->id, 'action' => 'knowledge.innovation.milestone-verified']);

        $otherOfficer = User::factory()->countyOfficial($otherCounty)->create();
        $this->actingAs($otherOfficer)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page->where('innovations.total', 0));
    }

    public function test_scoped_innovation_register_exports_all_supported_formats(): void
    {
        [$county, $innovation, , $reviewer] = $this->screeningInnovation();

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($reviewer)->get(route('workspace.export', ['knowledge-innovations',
                $format,
                'county_id' => $county->id,
                'status' => 'screening',
            ]));

            $response->assertOk();
            $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        }

        $csv = $this->actingAs($reviewer)->get(route('workspace.export', ['knowledge-innovations', 'csv', 'county_id' => $county->id, 'status' => 'screening']))->streamedContent();
        $this->assertStringContainsString('Reference release', $csv);
        $this->assertStringContainsString($innovation->referenceDataRelease()->firstOrFail()->checksum, $csv);

        $otherCounty = County::factory()->create();
        $this->actingAs($reviewer)->get(route('workspace.export', ['knowledge-innovations', 'json', 'county_id' => $otherCounty->id]))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['subject_id' => $reviewer->id, 'action' => 'workspace.exported']);
    }

    /** @return array{County, DevolutionInnovation, User, User, User} */
    private function screeningInnovation(): array
    {
        $county = County::factory()->create();
        $submitter = User::factory()->countyOfficial($county)->create();
        $firstReviewer = User::factory()->topManagement()->create();
        $secondReviewer = User::factory()->platformAdmin()->create();
        User::factory()->devolutionAdmin()->create();
        $firstReviewer->assignedCounties()->attach($county);
        $this->seed(KnowledgeWorkflowSeeder::class);
        $release = $this->publishedReferenceRelease($county, $submitter);
        $this->actingAs($submitter)->post(route('knowledge.innovations.store'), $this->innovationPayload($county))->assertRedirect();
        $innovation = DevolutionInnovation::query()->sole();
        $this->assertSame($release->id, $innovation->reference_data_release_id);
        $this->actingAs($submitter)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'submit', 'rationale' => 'Problem, solution and intended impact are ready for independent screening.'])->assertRedirect();

        return [$county, $innovation, $submitter, $firstReviewer, $secondReviewer];
    }

    /** @return array{County, DevolutionInnovation, User, User, User} */
    private function incubatingInnovation(): array
    {
        [$county, $innovation, $submitter, $firstReviewer, $secondReviewer] = $this->screeningInnovation();
        $this->actingAs($firstReviewer)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload())->assertRedirect();
        $this->actingAs($secondReviewer)->post(route('knowledge.innovations.panel-reviews.store', [$innovation]), $this->reviewPayload())->assertRedirect();
        $this->actingAs($firstReviewer)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'accept_incubation', 'rationale' => 'The independent panel threshold has been met.'])->assertRedirect();

        return [$county, $innovation->refresh(), $submitter, $firstReviewer, $secondReviewer];
    }

    /** @return array{County, DevolutionInnovation, User, User, User, InnovationExperimentMilestone} */
    private function pilotingInnovation(): array
    {
        [$county, $innovation, $owner, $curator] = $this->incubatingInnovation();
        $manager = User::factory()->devolutionAdmin()->create();
        $this->actingAs($manager)->post(route('knowledge.innovations.funding-decisions.store', [$innovation]), $this->fundingPayload())->assertRedirect();
        $this->actingAs($manager)->post(route('knowledge.innovations.milestones.store', [$innovation]), $this->milestonePayload($owner))->assertRedirect();
        $milestone = InnovationExperimentMilestone::query()->sole();
        $this->actingAs($manager)->patch(route('knowledge.innovations.transition', [$innovation]), ['transition' => 'start_pilot', 'rationale' => 'Funding and experiment protocol are approved.'])->assertRedirect();

        return [$county, $innovation->refresh(), $owner, $curator, $manager, $milestone];
    }

    /** @return array<string, mixed> */
    private function innovationPayload(County $county): array
    {
        return ['county_id' => $county->id, 'title' => 'Offline ward participation capture', 'problem_statement' => 'Low-connectivity wards cannot reliably submit participation records.', 'proposed_solution' => 'An offline-first signed capture workflow with deferred synchronization.', 'expected_impact' => 'Higher inclusion and complete provenance for ward submissions.', 'maturity_level' => 'prototype', 'incubation_support' => 'Security review and controlled pilot.', 'evidence_reference' => 'INNOVATION-EVIDENCE-001'];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function reviewPayload(array $overrides = []): array
    {
        return [...['strategic_fit_score' => 85, 'feasibility_score' => 80, 'inclusion_score' => 90, 'evidence_score' => 75, 'recommendation' => 'advance', 'rationale' => 'The proposal addresses a documented inclusion gap with credible controls and measurable evidence.'], ...$overrides];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fundingPayload(array $overrides = []): array
    {
        return [...['decision' => 'approved', 'amount' => 1500000, 'currency' => 'KES', 'funding_type' => 'grant', 'decision_reference' => 'IFD-2026-001', 'rationale' => 'Funding is approved against the independently screened experiment protocol and defined public-value outcomes.'], ...$overrides];
    }

    /** @return array<string, mixed> */
    private function milestonePayload(User $owner): array
    {
        return ['owner_id' => $owner->id, 'title' => 'Validate offline completion and inclusion', 'hypothesis' => 'Signed offline capture increases complete ward submissions without weakening provenance.', 'success_metric' => 'Complete verified ward submissions', 'baseline_value' => '54%', 'target_value' => '75%', 'due_at' => now()->addMonth()->toDateString()];
    }

    private function publishedReferenceRelease(County $county, User $approver): ReferenceDataRelease
    {
        $snapshot = ['counties' => [['id' => $county->id]], 'organizations' => [], 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];

        return ReferenceDataRelease::factory()->create([
            'version' => ((int) ReferenceDataRelease::query()->max('version')) + 1,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-INNOVATION-001',
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
