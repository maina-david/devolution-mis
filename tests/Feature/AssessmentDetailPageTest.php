<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssessmentDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_assessment_operator_workspace(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($admin)->get(route('assessments.show', [$admin->currentTeam->slug, $assessment]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('assessments/show')
                ->where('assessment.id', $assessment->id)
                ->where('assessment.county.id', $county->id)
                ->where('capabilities.submit', true));
    }

    public function test_county_user_cannot_open_assessment_outside_their_county(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $official = User::factory()->countyOfficial($home)->create();
        $assessment = Assessment::factory()->create(['county_id' => $other->id]);

        $this->actingAs($official)->get(route('assessments.show', [$official->currentTeam->slug, $assessment]))->assertForbidden();
    }
}
