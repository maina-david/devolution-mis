<?php

namespace Tests\Feature;

use App\Actions\DecideAssessmentAppeal;
use App\Actions\PublishAssessmentResult;
use App\Actions\ResolveAssessmentFinding;
use App\Actions\RespondToAssessmentFinding;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentAttestation;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\AssessmentCycle;
use App\Models\AssessmentFinding;
use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecardVersion;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\County;
use App\Models\User;
use App\Services\AssessmentBenchmarkService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentPublicationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_attested_result_is_published_as_reproducible_snapshot_and_ranked(): void
    {
        [$assessment, $actor] = $this->publishableAssessment(82);

        $publication = app(PublishAssessmentResult::class)->handle($assessment, $actor);
        $ranking = app(AssessmentBenchmarkService::class)->rankings($assessment->assessment_cycle_id);

        $this->assertSame('82.00', $publication->score);
        $this->assertSame('Meets standard', $publication->performance_band);
        $this->assertSame(64, strlen($publication->checksum));
        $this->assertSame('F01', $publication->function_profile[0]['code']);
        $this->assertEquals(82.0, $publication->function_profile[0]['score']);
        $this->assertSame(1, $ranking[0]['rank']);
        $this->assertSame(100.0, $ranking[0]['percentile']);
        $this->assertSame(AssessmentStatus::Published, $assessment->fresh()?->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.result_published']);
    }

    public function test_open_finding_and_pending_appeal_block_publication_until_governed_resolution(): void
    {
        [$assessment, $actor] = $this->publishableAssessment(76);
        $finding = AssessmentFinding::factory()->create(['assessment_id' => $assessment->id, 'raised_by' => $actor->id, 'status' => 'open']);
        $appeal = AssessmentAppeal::factory()->create(['assessment_id' => $assessment->id, 'appellant_id' => $actor->id, 'status' => 'submitted']);

        try {
            app(PublishAssessmentResult::class)->handle($assessment, $actor);
            $this->fail('Publication should be blocked by unresolved governance records.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('publication', $exception->errors());
        }

        $countyResponder = User::factory()->countyAdmin($assessment->county)->create();
        $independentReviewer = User::factory()->assessor()->create();
        $appealDecisionMaker = User::factory()->topManagement()->create();
        $independentReviewer->assignedCounties()->attach($assessment->county_id);
        $appealDecisionMaker->assignedCounties()->attach($assessment->county_id);
        app(RespondToAssessmentFinding::class)->handle($finding, $countyResponder, 'The county supplied the signed source register and reconciliation.');
        app(ResolveAssessmentFinding::class)->handle($finding->refresh(), $independentReviewer, 'The additional primary record resolves the finding completely.');
        app(DecideAssessmentAppeal::class)->handle($appeal, $appealDecisionMaker, 'rejected', 'The verified evidence and calculation were correctly applied; no adjustment is warranted.');

        $publication = app(PublishAssessmentResult::class)->handle($assessment->refresh(), $actor);
        $this->assertModelExists($publication);
        $this->assertSame('resolved', $finding->fresh()?->status);
        $this->assertSame('rejected', $appeal->fresh()?->status);
    }

    public function test_published_result_snapshot_is_database_immutable(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability triggers are database specific.');
        }
        [$assessment, $actor] = $this->publishableAssessment(90);
        $publication = app(PublishAssessmentResult::class)->handle($assessment, $actor);

        $this->expectException(QueryException::class);
        $publication->update(['score' => 1]);
    }

    public function test_publication_endpoint_enforces_permission_and_county_scope(): void
    {
        [$assessment, $nationalAdmin] = $this->publishableAssessment(88);
        $countyAdmin = User::factory()->countyAdmin($assessment->county)->create();

        $this->actingAs($countyAdmin)->post(route('assessments.publish', [$assessment]))->assertForbidden();
        $this->assertDatabaseCount('assessment_result_publications', 0);

        $this->actingAs($nationalAdmin)->post(route('assessments.publish', [$assessment]))->assertRedirect();
        $this->assertDatabaseHas('assessment_result_publications', ['assessment_id' => $assessment->id, 'score' => 88]);
    }

    /** @return array{Assessment, User} */
    private function publishableAssessment(float $score): array
    {
        $version = AssessmentScorecardVersion::factory()->create(['status' => 'draft', 'performance_thresholds' => [['label' => 'Meets standard', 'minimum' => 70, 'maximum' => 100], ['label' => 'Needs improvement', 'minimum' => 0, 'maximum' => 69.99]]]);
        $function = AssessmentFunction::factory()->create(['assessment_scorecard_version_id' => $version->id, 'code' => 'F01', 'weight' => 100]);
        $theme = AssessmentThematicArea::factory()->create(['assessment_function_id' => $function->id, 'weight' => 100]);
        $standard = AssessmentStandard::factory()->create(['assessment_thematic_area_id' => $theme->id, 'weight' => 100]);
        $criterion = AssessmentCriterion::factory()->create(['assessment_standard_id' => $standard->id, 'weight' => 100, 'maximum_score' => 100]);
        $version->update(['status' => 'published', 'checksum' => fake()->sha256(), 'published_at' => now(), 'effective_from' => now()]);
        $cycle = AssessmentCycle::factory()->create(['assessment_scorecard_version_id' => $version->id]);
        $county = County::factory()->create();
        $actor = User::factory()->devolutionAdmin()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $version->id, 'cycle' => $cycle->code, 'status' => AssessmentStatus::Approved, 'score' => $score, 'completeness_percentage' => 100, 'attestation_status' => 'attested']);
        AssessmentCriterionResult::factory()->create(['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id, 'verified_score' => $score, 'weighted_score' => $score, 'calculation_snapshot' => ['effective_score' => $score]]);
        AssessmentAttestation::factory()->create(['assessment_id' => $assessment->id, 'attested_by' => $actor->id]);

        return [$assessment, $actor];
    }
}
