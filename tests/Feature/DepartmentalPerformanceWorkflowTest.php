<?php

namespace Tests\Feature;

use App\Models\DocumentLink;
use App\Models\Organization;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalAmendmentDecision;
use App\Models\PerformancePlan;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Support\CanonicalJson;
use Database\Seeders\PerformanceWorkflowSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DepartmentalPerformanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_goal_plan_runs_through_independent_appraisal_and_capacity_gap_review(): void
    {
        Storage::fake('local');
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $cycle = $this->cycle($publisher);
        $this->seed(PerformanceWorkflowSeeder::class);

        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $this->planPayload($cycle, $supervisor))->assertRedirect();
        $plan = PerformancePlan::query()->with('goals')->sole();
        $this->assertTrue(Str::isUuid($plan->id));
        $this->assertCount(2, $plan->goals);
        $this->assertSame('draft', $plan->status);
        $this->assertNotNull($plan->workflow_instance_id);
        $this->assertNotNull($plan->reference_data_release_id);

        $this->transition($employee, $plan, ['transition' => 'submit_goals', 'rationale' => 'A narrative alone must not satisfy the signed goal-plan gate.'])->assertSessionHasErrors('transition');
        $this->uploadRecord($employee, $plan, 'goal_plan', 'Signed annual performance goal plan', 'scanned');
        $this->transition($employee, $plan, ['transition' => 'submit_goals', 'rationale' => 'Weighted goals and signed plan submitted for agreement.'])->assertRedirect();
        $this->transition($supervisor, $plan, ['transition' => 'approve_goals', 'rationale' => 'Goals align to departmental priorities and measurable results.'])->assertRedirect();
        $this->transition($employee, $plan, ['transition' => 'start_review', 'rationale' => 'The appraisal period is complete and self-review has started.'])->assertRedirect();
        $selfRatings = $plan->goals->values()->map(fn ($goal, int $index): array => ['id' => $goal->id, 'actual_value' => $index === 0 ? 82 : 19, 'rating' => $index === 0 ? 80 : 90, 'narrative' => 'Results reconciled against the signed departmental evidence register.', 'evidence_reference' => 'DMS/PERF/2026/'.($index + 1)])->all();
        $this->transition($employee, $plan, ['transition' => 'submit_self_review', 'rationale' => 'Text references must not replace retained self-review evidence.', 'goals' => $selfRatings])->assertSessionHasErrors('transition');
        $this->uploadRecord($employee, $plan, 'self_review_evidence', 'Employee self-review evidence register', 'digital');
        $this->transition($employee, $plan, ['transition' => 'submit_self_review', 'rationale' => 'Self-review and repository evidence submitted.', 'goals' => $selfRatings])->assertRedirect();
        $this->assertSame('84.00', $plan->refresh()->self_score);

        $this->transition($employee, $plan, ['transition' => 'finalize_review', 'rationale' => 'Attempted self-finalization.', 'capacity_gaps' => 'None', 'development_actions' => 'None', 'goals' => $selfRatings])->assertForbidden();
        $finalRatings = $plan->goals->values()->map(fn ($goal, int $index): array => ['id' => $goal->id, 'rating' => $index === 0 ? 70 : 95, 'narrative' => 'Supervisor rating verified against the KPI evidence.'])->all();
        $this->transition($supervisor, $plan, ['transition' => 'finalize_review', 'rationale' => 'A final score cannot replace the signed appraisal record.', 'capacity_gaps' => 'Advanced data visualization and contract-management capability.', 'development_actions' => 'Enroll in approved analytics course and assign a mentored procurement review.', 'goals' => $finalRatings])->assertSessionHasErrors('transition');
        $this->uploadRecord($supervisor, $plan, 'final_appraisal', 'Signed final performance appraisal', 'scanned');
        $this->transition($supervisor, $plan, ['transition' => 'finalize_review', 'rationale' => 'Final appraisal independently completed.', 'capacity_gaps' => 'Advanced data visualization and contract-management capability.', 'development_actions' => 'Enroll in approved analytics course and assign a mentored procurement review.', 'goals' => $finalRatings])->assertRedirect();

        $plan->refresh();
        $this->assertSame('finalized', $plan->status);
        $this->assertSame('80.00', $plan->final_score);
        $this->assertNotNull($plan->finalized_at);
        $this->assertDatabaseHas('performance_reviews', ['performance_plan_id' => $plan->id, 'reviewer_id' => $supervisor->id, 'stage' => 'finalize_review']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'performance.plan.transitioned']);
        $links = DocumentLink::query()->with('document')->where('subject_id', $plan->id)->orderBy('purpose')->get();
        $this->assertSame(['performance-final-appraisal', 'performance-goal-plan', 'performance-self-review-evidence'], $links->pluck('purpose')->all());
        $links->each(function (DocumentLink $link): void {
            $this->assertSame('clean', $link->document->scan_status);
            Storage::disk('local')->assertExists($link->document->path);
        });
        $this->actingAs($supervisor)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('plans.data.0.documents', 3));
        $this->actingAs($supervisor)->get(route('evidence.preview', [$links->first()->document]))->assertOk();
        $outside = User::factory()->topManagement()->create();
        $this->actingAs($outside)->get(route('evidence.preview', [$links->first()->document]))->assertForbidden();
        $this->actingAs($employee)->get(route('evidence.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.pagination.total', 3));
        $this->actingAs($supervisor)->post(route('departmental-performance.plans.documents.store', [$plan]), [
            'record_purpose' => 'final_appraisal', 'title' => 'Late appraisal record', 'category' => 'Performance appraisal', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late-appraisal.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($supervisor)->get(route('workspace.export', ['departmental-performance', $format]))->assertOk()->assertDownload();
        }
        $release = $plan->referenceDataRelease()->sole();
        $csv = $this->actingAs($supervisor)->get(route('workspace.export', ['departmental-performance', 'csv']))->streamedContent();
        $this->assertStringContainsString('Reference release', $csv);
        $this->assertStringContainsString($release->checksum, $csv);
        $this->actingAs($supervisor)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('catalogue.available', true)
            ->where('plans.data.0.referenceData.version', $release->version)
            ->where('plans.data.0.referenceData.checksum', $release->checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'performance.plan.created']);
    }

    public function test_plan_creation_requires_a_checksum_valid_catalogue_and_governed_organization(): void
    {
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $organization = Organization::factory()->create();
        $cycle = $this->cycle($publisher, false);
        $this->seed(PerformanceWorkflowSeeder::class);
        $payload = $this->planPayload($cycle, $supervisor);
        $payload['organization_id'] = $organization->id;

        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('performance_plans', 0);

        $this->publishedReferenceRelease($publisher, [], str_repeat('0', 64));
        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('performance_plans', 0);

        $this->publishedReferenceRelease($publisher);
        try {
            app(EffectiveReferenceDataReleaseResolver::class)->forPerformancePlan($organization->id, now());
            $this->fail('An organization outside the effective snapshot must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('organization_id', $exception->errors());
        }
        $this->assertDatabaseCount('performance_plans', 0);

        $expectedRelease = $this->publishedReferenceRelease($publisher, [$organization]);
        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $payload)->assertRedirect();
        $this->assertSame($expectedRelease->id, PerformancePlan::query()->sole()->reference_data_release_id);
    }

    public function test_goal_weights_must_total_one_hundred(): void
    {
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $cycle = $this->cycle($publisher);
        $this->seed(PerformanceWorkflowSeeder::class);
        $payload = $this->planPayload($cycle, $supervisor);
        $payload['goals'][1]['weight'] = 20;

        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $payload)->assertSessionHasErrors('goals');
        $this->assertDatabaseCount('performance_plans', 0);
    }

    public function test_active_goal_amendments_are_versioned_independently_approved_and_immutable(): void
    {
        Storage::fake('local');
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $outsider = User::factory()->topManagement()->create();
        $cycle = $this->cycle($publisher);
        $this->seed(PerformanceWorkflowSeeder::class);

        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $this->planPayload($cycle, $supervisor))->assertRedirect();
        $plan = PerformancePlan::query()->with('goals.versions')->sole();
        $goal = $plan->goals->first();
        $initialVersion = $goal->versions->sole();
        $this->assertTrue(Str::isUuid($initialVersion->id));
        $this->assertSame($employee->id, $initialVersion->created_by);
        $this->assertSame(64, strlen($initialVersion->version_checksum));

        $this->uploadRecord($employee, $plan, 'goal_plan', 'Signed amendment baseline goal plan', 'scanned');
        $this->transition($employee, $plan, ['transition' => 'submit_goals', 'rationale' => 'Submit signed baseline goals for supervisor agreement.'])->assertRedirect();
        $this->transition($supervisor, $plan, ['transition' => 'approve_goals', 'rationale' => 'Approve the signed baseline before controlled execution.'])->assertRedirect();

        $payload = [...$initialVersion->definition_snapshot, 'target_value' => 97, 'reason' => 'The approved annual delivery target increased after a documented executive commitment.'];
        $amendmentRoute = route('departmental-performance.plans.goals.amendments.store', [$plan, $goal]);
        $this->actingAs($outsider)->post($amendmentRoute, $payload)->assertForbidden();
        $this->actingAs($employee)->post($amendmentRoute, $payload)->assertRedirect();

        $amendment = PerformanceGoalAmendment::query()->sole();
        $this->assertTrue(Str::isUuid($amendment->id));
        $this->assertSame($initialVersion->id, $amendment->base_version_id);
        $this->assertSame(64, strlen($amendment->request_checksum));
        $this->assertSame('95.0000', $goal->refresh()->target_value);

        $decisionRoute = route('departmental-performance.plans.goal-amendments.decisions.store', [$plan, $amendment]);
        $supervisorDecisionRoute = route('departmental-performance.plans.goal-amendments.decisions.store', [$plan, $amendment]);
        $decisionPayload = ['decision' => 'approved', 'rationale' => 'The revised target is measurable and the total weighting remains exactly one hundred percent.'];
        $this->actingAs($employee)->post($decisionRoute, $decisionPayload)->assertForbidden();
        $this->actingAs($outsider)->post($decisionRoute, $decisionPayload)->assertForbidden();
        $this->actingAs($supervisor)->post($supervisorDecisionRoute, $decisionPayload)->assertRedirect();

        $decision = PerformanceGoalAmendmentDecision::query()->sole();
        $goal->refresh()->load('versions');
        $appliedVersion = $goal->versions->first();
        $this->assertSame('97.0000', $goal->target_value);
        $this->assertCount(2, $goal->versions);
        $this->assertSame($initialVersion->version_checksum, $appliedVersion->predecessor_checksum);
        $this->assertSame($appliedVersion->id, $decision->applied_version_id);
        $this->assertSame(64, strlen($decision->decision_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'performance.goal.amendment_requested']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'performance.goal.amendment_decided']);
        $this->actingAs($supervisor)->post($supervisorDecisionRoute, $decisionPayload)->assertStatus(409);
        $this->actingAs($supervisor)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('plans.data.0.goals.0.versions', 2)
            ->has('plans.data.0.goals.0.amendments', 1)
            ->where('plans.data.0.goals.0.amendments.0.decision.decision', 'approved'));

        try {
            $appliedVersion->update(['version' => 3]);
            $this->fail('Governance history must be database immutable.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function test_amendment_approval_rejects_a_plan_weight_below_one_hundred(): void
    {
        Storage::fake('local');
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $cycle = $this->cycle($publisher);
        $this->seed(PerformanceWorkflowSeeder::class);
        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $this->planPayload($cycle, $supervisor))->assertRedirect();
        $plan = PerformancePlan::query()->with('goals.versions')->sole();
        $goal = $plan->goals->first();
        $this->uploadRecord($employee, $plan, 'goal_plan', 'Signed weighted goal plan', 'digital');
        $this->transition($employee, $plan, ['transition' => 'submit_goals', 'rationale' => 'Submit signed baseline goals for approval.'])->assertRedirect();
        $this->transition($supervisor, $plan, ['transition' => 'approve_goals', 'rationale' => 'Approve the complete weighted plan.'])->assertRedirect();
        $payload = [...$goal->versions->first()->definition_snapshot, 'weight' => 50, 'reason' => 'A material reweighting was requested for supervisor consideration and control.'];
        $this->actingAs($employee)->post(route('departmental-performance.plans.goals.amendments.store', [$plan, $goal]), $payload)->assertRedirect();
        $amendment = PerformanceGoalAmendment::query()->sole();
        $this->actingAs($supervisor)->post(route('departmental-performance.plans.goal-amendments.decisions.store', [$plan, $amendment]), [
            'decision' => 'approved', 'rationale' => 'Attempt to approve a plan whose resulting total weighting is only ninety percent.',
        ])->assertStatus(422);
        $this->assertSame('60.00', $goal->refresh()->weight);
        $this->assertDatabaseCount('performance_goal_amendment_decisions', 0);
    }

    public function test_only_employee_supervisor_or_national_administrator_can_view_or_mutate_plan(): void
    {
        $publisher = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $outsider = User::factory()->topManagement()->create();
        $cycle = $this->cycle($publisher);
        $this->seed(PerformanceWorkflowSeeder::class);
        $this->actingAs($employee)->post(route('departmental-performance.plans.store'), $this->planPayload($cycle, $supervisor))->assertRedirect();
        $plan = PerformancePlan::query()->sole();

        $this->actingAs($supervisor)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('departmental-performance/index')->where('plans.total', 1));
        $this->actingAs($outsider)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('plans.total', 0));
        $this->transition($outsider, $plan, ['transition' => 'approve_goals', 'rationale' => 'Unauthorized review attempt.'])->assertForbidden();
    }

    public function test_analytics_only_aggregate_finalized_plans_in_the_users_visible_scope(): void
    {
        $creator = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->topManagement()->create();
        $supervisor = User::factory()->topManagement()->create();
        $outsider = User::factory()->topManagement()->create();
        $cycle = $this->cycle($creator);
        PerformancePlan::factory()->create(['performance_cycle_id' => $cycle->id, 'employee_id' => $employee->id, 'supervisor_id' => $supervisor->id, 'status' => 'finalized', 'final_score' => 84, 'capacity_gap_summary' => 'Data visualization']);
        PerformancePlan::factory()->create(['performance_cycle_id' => $cycle->id, 'employee_id' => $outsider->id, 'supervisor_id' => $supervisor->id, 'status' => 'finalized', 'final_score' => 20, 'capacity_gap_summary' => 'Procurement']);

        $this->actingAs($employee)->get(route('departmental-performance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('analytics.summary.finalized', 1)
            ->where('analytics.summary.averageScore', 84)
            ->where('analytics.capacityGaps.0.gap', 'Data visualization')
            ->missing('analytics.capacityGaps.1'));
    }

    public function test_deadline_reminders_and_overdue_escalations_are_idempotent_and_audited(): void
    {
        Notification::fake();
        $creator = User::factory()->devolutionAdmin()->create();
        $employee = User::factory()->devolutionAdmin()->create();
        $supervisor = User::factory()->topManagement()->create();
        $cycle = $this->cycle($creator);
        $plan = PerformancePlan::factory()->create(['performance_cycle_id' => $cycle->id, 'employee_id' => $employee->id, 'supervisor_id' => $supervisor->id, 'status' => 'supervisor_review', 'decision_due_at' => now()->subHour()]);

        $this->artisan('departmental-performance:send-reminders')->assertSuccessful();
        $this->assertNotNull($plan->refresh()->reminder_sent_at);
        $this->assertNotNull($plan->escalated_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $plan->id, 'action' => 'performance.plan.escalated']);
        Notification::assertSentToTimes($employee, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($supervisor, ProgrammeAlert::class, 1);

        $this->artisan('departmental-performance:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($employee, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($supervisor, ProgrammeAlert::class, 1);
    }

    private function cycle(User $creator, bool $publishReferenceRelease = true): PerformanceCycle
    {
        $cycle = PerformanceCycle::create(['code' => 'FY2026-27', 'name' => 'FY 2026/27 Performance Cycle', 'period_start' => '2026-07-01', 'period_end' => '2027-06-30', 'goal_setting_deadline' => '2026-08-31', 'midterm_review_deadline' => '2027-01-31', 'final_review_deadline' => '2027-06-30', 'status' => 'open', 'created_by' => $creator->id]);
        if ($publishReferenceRelease) {
            $this->publishedReferenceRelease($creator);
        }

        return $cycle;
    }

    /** @param list<Organization> $organizations */
    private function publishedReferenceRelease(User $approver, array $organizations = [], ?string $checksum = null): ReferenceDataRelease
    {
        $snapshot = ['counties' => [], 'organizations' => array_map(fn (Organization $organization): array => ['id' => $organization->id], $organizations), 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => $checksum ?? app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-PERFORMANCE-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function planPayload(PerformanceCycle $cycle, User $supervisor): array
    {
        return ['performance_cycle_id' => $cycle->id, 'supervisor_id' => $supervisor->id, 'plan_type' => 'individual', 'hris_employee_reference' => 'IPPD-SDD-001', 'job_title' => 'Programme Officer', 'overall_expectations' => 'Deliver traceable programme results, reliable reporting and responsive stakeholder coordination.', 'goals' => [
            ['code' => 'KPI-01', 'title' => 'Timely programme reporting', 'description' => 'Submit complete validated programme reports within the approved calendar.', 'kpi' => 'Reports submitted on time', 'unit_of_measure' => 'percent', 'baseline_value' => 65, 'target_value' => 95, 'weight' => 60],
            ['code' => 'KPI-02', 'title' => 'County capacity support', 'description' => 'Complete evidence-backed county capacity support engagements.', 'kpi' => 'County engagements completed', 'unit_of_measure' => 'count', 'baseline_value' => 12, 'target_value' => 20, 'weight' => 40],
        ]];
    }

    /** @param array<string, mixed> $payload */
    private function transition(User $actor, PerformancePlan $plan, array $payload): TestResponse
    {
        return $this->actingAs($actor)->patch(route('departmental-performance.plans.transition', [$plan]), $payload);
    }

    private function uploadRecord(User $actor, PerformancePlan $plan, string $purpose, string $title, string $sourceType): void
    {
        $this->actingAs($actor)->post(route('departmental-performance.plans.documents.store', [$plan]), [
            'record_purpose' => $purpose,
            'title' => $title,
            'category' => 'Performance appraisal',
            'source_type' => $sourceType,
            'document' => UploadedFile::fake()->create(str($title)->slug()->append('.pdf')->toString(), 20, 'application/pdf'),
        ])->assertRedirect();
    }
}
