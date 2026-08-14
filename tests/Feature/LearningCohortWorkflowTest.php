<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\LearningCohort;
use App\Models\LearningCohortMembership;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

class LearningCohortWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_capacity_governed_cohort_adds_enrollment_and_completes_lifecycle(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $county = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $instructor = User::factory()->devolutionAdmin()->create();
        $learner = User::factory()->countyOfficial($county)->create();
        $course = LearningCourse::factory()->create(['status' => 'published']);
        $enrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $learner->id, 'county_id' => $county->id, 'enrolled_by' => $manager->id]);

        $this->withSession(['locale' => 'fr']);
        $this->actingAs($manager)->post(route('learning.cohorts.store'), $this->payload($course, $instructor, $county))->assertRedirect()->assertSessionHasNoErrors();
        $cohort = LearningCohort::query()->sole();
        $this->assertTrue(Str::isUuid($cohort->id));
        $this->assertSame('draft', $cohort->status);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $cohort->id,
            'action' => 'learning.cohort.created',
            'description' => "Cohorte de formation {$cohort->code} créée pour {$course->code}.",
        ]);

        $this->actingAs($manager)->post(route('learning.cohorts.members.store', [$cohort]), ['learning_enrollment_id' => $enrollment->id])->assertRedirect()->assertSessionHasNoErrors();
        $membership = LearningCohortMembership::query()->sole();
        $this->assertTrue(Str::isUuid($membership->id));
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $membership->id,
            'action' => 'learning.cohort.member-added',
            'description' => "{$learner->name} ajouté à la cohorte de formation {$cohort->code}.",
        ]);

        $this->transition($manager, $cohort, 'open')->assertRedirect();
        Carbon::setTestNow('2026-09-11 09:00:00');
        $this->transition($manager, $cohort, 'start')->assertRedirect();
        $this->actingAs($manager)->post(route('learning.cohorts.members.store', [$cohort]), ['learning_enrollment_id' => $enrollment->id])->assertStatus(409);
        Carbon::setTestNow('2026-10-02 09:00:00');
        $this->transition($manager, $cohort, 'complete')->assertRedirect();
        $this->assertSame('completed', $cohort->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $cohort->id,
            'action' => 'learning.cohort.transitioned',
            'description' => "Cohorte de formation {$cohort->code} passée au statut terminée.",
        ]);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($manager)->get(route('workspace.export', ['learning-cohorts', $format]))->assertOk()->assertDownload();
        }
        $this->actingAs($manager)->get(route('learning.index'))->assertOk()->assertInertia(fn ($page) => $page->where('cohorts.total', 1)->where('cohorts.data.0.membersCount', 1)->where('cohorts.data.0.instructor', $instructor->name));
    }

    public function test_cross_course_instructor_and_schedule_invariants_fail_closed(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $county = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $instructor = User::factory()->devolutionAdmin()->create();
        $unauthorizedInstructor = User::factory()->countyOfficial($county)->create();
        $course = LearningCourse::factory()->create(['status' => 'published']);
        $otherCourse = LearningCourse::factory()->create(['status' => 'published']);
        $learner = User::factory()->countyOfficial($county)->create();
        $otherEnrollment = LearningEnrollment::factory()->create(['learning_course_id' => $otherCourse->id, 'user_id' => $learner->id, 'county_id' => $county->id]);

        $this->actingAs($manager)->post(route('learning.cohorts.store'), $this->payload($course, $unauthorizedInstructor, $county))->assertStatus(422);
        $this->assertDatabaseCount('learning_cohorts', 0);

        $payload = $this->payload($course, $instructor, $county);
        $payload['capacity'] = 1;
        $this->actingAs($manager)->post(route('learning.cohorts.store'), $payload)->assertRedirect();
        $cohort = LearningCohort::query()->sole();
        $this->actingAs($manager)->post(route('learning.cohorts.members.store', [$cohort]), ['learning_enrollment_id' => $otherEnrollment->id])->assertStatus(422);
        $this->transition($manager, $cohort, 'open')->assertRedirect();
        $this->transition($manager, $cohort, 'start')->assertStatus(409);
        $this->assertSame('open', $cohort->refresh()->status);
    }

    public function test_county_scope_limits_visibility_and_mutation(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $targetCounty = County::factory()->create();
        $otherCounty = County::factory()->create();
        $nationalManager = User::factory()->devolutionAdmin()->create();
        $instructor = User::factory()->devolutionAdmin()->create();
        $targetUser = User::factory()->countyAdmin($targetCounty)->create();
        $otherUser = User::factory()->countyAdmin($otherCounty)->create();
        $targetUser->givePermissionTo(ProgrammePermission::ManageLearning->value);
        $otherUser->givePermissionTo(ProgrammePermission::ManageLearning->value);
        $course = LearningCourse::factory()->create(['status' => 'published', 'county_id' => $targetCounty->id]);
        $learner = User::factory()->countyOfficial($targetCounty)->create();
        $enrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $learner->id, 'county_id' => $targetCounty->id]);

        $this->actingAs($nationalManager)->post(route('learning.cohorts.store'), $this->payload($course, $instructor, $targetCounty))->assertRedirect();
        $cohort = LearningCohort::query()->sole();
        $this->actingAs($targetUser)->get(route('learning.index'))->assertOk()->assertInertia(fn ($page) => $page->where('cohorts.total', 1));
        $this->actingAs($otherUser)->get(route('learning.index'))->assertOk()->assertInertia(fn ($page) => $page->where('cohorts.total', 0));
        $this->actingAs($otherUser)->post(route('learning.cohorts.members.store', [$cohort]), ['learning_enrollment_id' => $enrollment->id])->assertForbidden();
        $this->actingAs($targetUser)->post(route('learning.cohorts.members.store', [$cohort]), ['learning_enrollment_id' => $enrollment->id])->assertRedirect();
        $this->transition($otherUser, $cohort, 'open')->assertForbidden();
        $this->transition($targetUser, $cohort, 'open')->assertRedirect();
    }

    /** @return array<string, mixed> */
    private function payload(LearningCourse $course, User $instructor, County $county): array
    {
        return ['learning_course_id' => $course->id, 'instructor_id' => $instructor->id, 'county_id' => $county->id, 'code' => 'LRN-COHORT-2026-01', 'name' => 'County devolution delivery practitioners', 'description' => 'A governed instructor-led cohort for county practitioners completing the published devolution curriculum.', 'capacity' => 30, 'enrollment_opens_on' => '2026-09-02', 'enrollment_closes_on' => '2026-09-09', 'starts_at' => '2026-09-10T09:00:00+03:00', 'ends_at' => '2026-10-01T17:00:00+03:00'];
    }

    /** @return TestResponse<SymfonyResponse> */
    private function transition(User $actor, LearningCohort $cohort, string $transition): TestResponse
    {
        return $this->actingAs($actor)->patch(route('learning.cohorts.transition', [$cohort]), ['transition' => $transition, 'rationale' => 'The authorized cohort manager verified the schedule, roster and delivery evidence.']);
    }
}
