<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectivePlan;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFinding;
use App\Models\AssessmentResultPublication;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentCorrectiveActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_finding_drives_evidence_backed_corrective_action_to_independent_closure(): void
    {
        [$assessment, $finding, $document, $submitter, $reviewer, $closer, $owner] = $this->scenario();
        $publicationChecksum = $assessment->publication?->checksum;

        $this->actingAs($submitter)->post(route('assessments.corrective-plans.store', [$submitter->currentTeam->slug, $assessment]), $this->planPayload($finding, $owner))->assertRedirect();
        $plan = AssessmentCorrectivePlan::query()->sole();
        $correctiveAction = AssessmentCorrectiveAction::query()->sole();
        $this->assertSame(64, strlen($plan->checksum));
        $this->assertSame('submitted', $plan->status);

        $reviewPayload = ['decision' => 'activate', 'review_note' => 'The root-cause analysis, owner, indicator, target and timeframe are credible.'];
        $this->actingAs($submitter)->patch(route('assessments.corrective-plans.review', [$submitter->currentTeam->slug, $assessment, $plan]), $reviewPayload)->assertForbidden();
        $this->actingAs($reviewer)->patch(route('assessments.corrective-plans.review', [$reviewer->currentTeam->slug, $assessment, $plan]), $reviewPayload)->assertRedirect();

        $this->submitAndVerifyProgress($assessment, $plan, $correctiveAction, $document, $submitter, $reviewer, 50);
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.closure.store', [$submitter->currentTeam->slug, $assessment, $plan]))->assertStatus(409);
        $this->submitAndVerifyProgress($assessment, $plan, $correctiveAction, $document, $submitter, $reviewer, 100);
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.closure.store', [$submitter->currentTeam->slug, $assessment, $plan]))->assertRedirect();

        $closure = ['decision' => 'closed', 'decision_reason' => 'All actions have independently verified completion evidence and the intended control is operational.'];
        $this->actingAs($reviewer)->patch(route('assessments.corrective-plans.closure.decide', [$reviewer->currentTeam->slug, $assessment, $plan]), $closure)->assertForbidden();
        $this->actingAs($closer)->patch(route('assessments.corrective-plans.closure.decide', [$closer->currentTeam->slug, $assessment, $plan]), $closure)->assertRedirect();

        $this->assertSame('closed', $plan->fresh()->status);
        $this->assertSame('completed', $correctiveAction->fresh()->status);
        $this->assertSame('100.00', $correctiveAction->fresh()->progress_percentage);
        $this->assertSame($publicationChecksum, $assessment->publication?->fresh()->checksum);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'assessment.corrective_plan_submitted']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'assessment.corrective_plan_closed']);

        $this->actingAs($closer)->get(route('assessments.show', [$closer->currentTeam->slug, $assessment]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('assessment.correctivePlans.0.status', 'closed')
            ->where('assessment.correctivePlans.0.actions.0.progress', 100)
            ->has('assessment.correctivePlans.0.actions.0.updates', 2));

        $decidedUpdate = AssessmentCorrectiveUpdate::query()->where('status', 'verified')->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('assessment_corrective_updates')->where('id', $decidedUpdate->id)->update(['narrative' => 'Tampered evidence']);
    }

    public function test_corrective_plan_requires_published_result_and_governed_source(): void
    {
        $county = County::factory()->create();
        $submitter = User::factory()->countyAdmin($county)->create();
        $owner = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::Approved]);
        $finding = AssessmentFinding::factory()->create(['assessment_id' => $assessment->id, 'severity' => 'minor', 'status' => 'resolved']);

        $this->actingAs($submitter)->post(route('assessments.corrective-plans.store', [$submitter->currentTeam->slug, $assessment]), $this->planPayload($finding, $owner))->assertStatus(409);
        $this->assertDatabaseCount('assessment_corrective_plans', 0);
    }

    public function test_progress_rejects_cross_county_unverified_and_non_monotonic_evidence(): void
    {
        [$assessment, $finding, $document, $submitter, $reviewer, , $owner] = $this->scenario();
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.store', [$submitter->currentTeam->slug, $assessment]), $this->planPayload($finding, $owner))->assertRedirect();
        $plan = AssessmentCorrectivePlan::query()->sole();
        $correctiveAction = AssessmentCorrectiveAction::query()->sole();
        $this->actingAs($reviewer)->patch(route('assessments.corrective-plans.review', [$reviewer->currentTeam->slug, $assessment, $plan]), ['decision' => 'activate', 'review_note' => 'Approved.'])->assertRedirect();

        $otherCounty = County::factory()->create();
        $otherOfficial = User::factory()->countyAdmin($otherCounty)->create();
        $otherAssessment = Assessment::factory()->create(['county_id' => $otherCounty->id]);
        $otherDocument = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $otherCounty->id, 'verification_status' => 'verified', 'scan_status' => 'clean']);
        $updatePayload = ['assessment_document_id' => $otherDocument->id, 'progress_percentage' => 40, 'narrative' => 'Evidence from another county.'];
        $this->actingAs($otherOfficial)->post(route('assessments.corrective-plans.actions.updates.store', [$otherOfficial->currentTeam->slug, $assessment, $plan, $correctiveAction]), $updatePayload)->assertForbidden();
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.actions.updates.store', [$submitter->currentTeam->slug, $assessment, $plan, $correctiveAction]), $updatePayload)->assertStatus(409);

        $this->submitAndVerifyProgress($assessment, $plan, $correctiveAction, $document, $submitter, $reviewer, 60);
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.actions.updates.store', [$submitter->currentTeam->slug, $assessment, $plan, $correctiveAction]), ['assessment_document_id' => $document->id, 'progress_percentage' => 50, 'narrative' => 'Regressive progress.'])->assertStatus(409);
    }

    /** @return array{Assessment, AssessmentFinding, AssessmentDocument, User, User, User, User} */
    private function scenario(): array
    {
        $county = County::factory()->create();
        $submitter = User::factory()->countyAdmin($county)->create();
        $reviewer = User::factory()->assessor()->create();
        $reviewer->assignedCounties()->attach($county);
        $closer = User::factory()->devolutionAdmin()->create();
        $owner = User::factory()->countyOfficial($county)->create();
        $cycle = AssessmentCycle::factory()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id, 'status' => AssessmentStatus::Published, 'published_at' => now()]);
        AssessmentResultPublication::factory()->create(['assessment_id' => $assessment->id, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id, 'county_id' => $county->id, 'published_by' => $closer->id]);
        $finding = AssessmentFinding::factory()->create(['assessment_id' => $assessment->id, 'severity' => 'major', 'status' => 'resolved', 'resolved_by' => $reviewer->id, 'resolved_at' => now()]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'verification_status' => 'verified', 'scan_status' => 'clean', 'record_status' => 'active']);

        return [$assessment->fresh() ?? $assessment, $finding, $document, $submitter, $reviewer, $closer, $owner];
    }

    /** @return array<string, mixed> */
    private function planPayload(AssessmentFinding $finding, User $owner): array
    {
        return ['assessment_finding_id' => $finding->id, 'reference' => 'CAP-ACPA-2026-001', 'title' => 'Close public participation evidence control gap', 'root_cause' => 'The county lacked a reconciled custody and publication control for ward participation records.', 'expected_outcome' => 'Every ward record is reconciled, approved and discoverable from the governed repository.', 'due_at' => now()->addMonth()->toDateString(), 'actions' => [['accountable_owner_id' => $owner->id, 'code' => 'CAP-A01', 'title' => 'Implement ward evidence reconciliation', 'description' => 'Reconcile, approve and publish the ward evidence register.', 'success_indicator' => 'Share of ward records reconciled and approved', 'target' => '100 percent', 'due_at' => now()->addWeeks(3)->toDateString()]]];
    }

    private function submitAndVerifyProgress(Assessment $assessment, AssessmentCorrectivePlan $plan, AssessmentCorrectiveAction $action, AssessmentDocument $document, User $submitter, User $reviewer, float $progress): void
    {
        $this->actingAs($submitter)->post(route('assessments.corrective-plans.actions.updates.store', [$submitter->currentTeam->slug, $assessment, $plan, $action]), ['assessment_document_id' => $document->id, 'progress_percentage' => $progress, 'narrative' => "Verified implementation progress reached {$progress} percent."])->assertRedirect();
        $update = AssessmentCorrectiveUpdate::query()->where('status', 'pending_verification')->sole();
        $this->actingAs($submitter)->patch(route('assessments.corrective-plans.actions.updates.verify', [$submitter->currentTeam->slug, $assessment, $plan, $action, $update]), ['decision' => 'verified', 'decision_note' => 'Attempted self-verification.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('assessments.corrective-plans.actions.updates.verify', [$reviewer->currentTeam->slug, $assessment, $plan, $action, $update]), ['decision' => 'verified', 'decision_note' => 'Repository evidence supports the reported progress.'])->assertRedirect();
    }
}
