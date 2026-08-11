<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\County;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssessmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_admin_can_submit_only_their_countys_assessment(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->countyAdmin($home)->create();
        $assessment = Assessment::factory()->create(['county_id' => $home->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $hidden = Assessment::factory()->create(['county_id' => $other->id, 'status' => AssessmentStatus::EvidenceCollection]);
        Notification::fake();

        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $assessment]))->assertRedirect();
        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $hidden]))->assertForbidden();

        $this->assertSame(AssessmentStatus::Submitted, $assessment->fresh()?->status);
        $this->assertSame(AssessmentStatus::EvidenceCollection, $hidden->fresh()?->status);
    }

    public function test_county_official_cannot_submit_an_assessment(): void
    {
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($official)->patch(route('assessments.submit', [$official->currentTeam->slug, $assessment]))->assertForbidden();
    }

    public function test_assessor_can_review_and_score_an_assigned_assessment(): void
    {
        $county = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::Submitted, 'score' => null]);
        Notification::fake();

        $this->actingAs($assessor)->patch(route('assessments.review', [$assessor->currentTeam->slug, $assessment]))->assertRedirect();
        $this->actingAs($assessor)->patch(route('assessments.score', [$assessor->currentTeam->slug, $assessment]), ['score' => 84.5])->assertRedirect();

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Assessed, $assessment->status);
        $this->assertSame('84.50', $assessment->score);
        $this->assertSame($assessor->id, $assessment->assessor_id);
        $this->assertNotNull($assessment->assessed_at);
    }

    public function test_assessment_score_must_be_between_zero_and_one_hundred(): void
    {
        $county = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::UnderAssessment]);

        $this->actingAs($assessor)->from(route('assessments.index', $assessor->currentTeam->slug))->patch(route('assessments.score', [$assessor->currentTeam->slug, $assessment]), ['score' => 101])->assertSessionHasErrors('score');
    }

    public function test_top_management_can_approve_an_assessed_county_in_its_portfolio(): void
    {
        $county = County::factory()->create();
        $manager = User::factory()->topManagement()->create();
        $manager->assignedCounties()->attach($county);
        $countyOfficial = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::Assessed]);
        Notification::fake();

        $this->actingAs($manager)->patch(route('assessments.approve', [$manager->currentTeam->slug, $assessment]))->assertRedirect();

        $this->assertSame(AssessmentStatus::Approved, $assessment->fresh()?->status);
        Notification::assertSentTo($countyOfficial, ProgrammeAlert::class);
    }

    public function test_invalid_state_transition_is_rejected(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::Approved]);

        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $assessment]))->assertStatus(409);
    }
}
