<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Models\VirtualClassroomAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VirtualClassroomAttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_records_duration_classified_idempotent_provider_attendance_with_capacity_control(): void
    {
        [$classroom, $facilitator, $firstEnrollment, $secondEnrollment] = $this->classroomWithRoster(capacity: 1);
        $payload = $this->presentPayload($classroom, $firstEnrollment, 'teams-event-001');

        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $payload)->assertRedirect();
        $attendance = VirtualClassroomAttendance::query()->sole();
        $this->assertTrue(Str::isUuid($attendance->id));
        $this->assertSame('present', $attendance->attendance_status);
        $this->assertSame(90, $attendance->attended_minutes);
        $this->assertSame(64, mb_strlen($attendance->payload_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $attendance->id, 'action' => 'learning.classroom_attendance_recorded']);

        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $payload)->assertRedirect();
        $this->assertDatabaseCount('virtual_classroom_attendances', 1);
        $this->assertSame(1, AuditEvent::query()
            ->where('subject_id', $attendance->id)
            ->where('action', 'learning.classroom_attendance_recorded')
            ->count());

        $conflictingEvent = [...$payload, 'attendance_status' => 'absent', 'joined_at' => null, 'left_at' => null];
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $conflictingEvent)->assertStatus(409);

        $capacityPayload = $this->presentPayload($classroom, $secondEnrollment, 'teams-event-002');
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $capacityPayload)->assertStatus(409);

        $misclassified = [...$capacityPayload, 'attendance_status' => 'partial', 'provider_event_id' => 'teams-event-003'];
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $misclassified)->assertSessionHasErrors('attendance_status');
    }

    public function test_roster_filters_county_scope_and_all_authorized_exports_are_enforced(): void
    {
        [$classroom, $facilitator, $enrollment] = $this->classroomWithRoster();
        $manager = User::factory()->devolutionAdmin()->create();
        $outsider = User::factory()->countyOfficial(County::factory()->create())->create();

        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $this->presentPayload($classroom, $enrollment, 'teams-event-filter'))->assertRedirect();

        $this->actingAs($facilitator)->get(route('learning.classrooms.show', [$facilitator->currentTeam->slug, $classroom, 'status' => 'present', 'search' => $enrollment->user->name]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('learning/classrooms/show')->where('roster.pagination.total', 1)->where('roster.rows.0.status', 'present')->where('roster.rows.0.cells.1.name', $enrollment->county->name));
        $this->actingAs($outsider)->get(route('learning.classrooms.show', [$outsider->currentTeam->slug, $classroom]))->assertForbidden();
        $this->actingAs($enrollment->user)->get(route('learning.classrooms.show', [$enrollment->user->currentTeam->slug, $classroom]))->assertForbidden();
        $this->actingAs($manager)->get(route('learning.classrooms.show', [$manager->currentTeam->slug, $classroom]))->assertOk();

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($facilitator)->get(route('workspace.export', [$facilitator->currentTeam->slug, 'learning-attendance', $format, 'classroom_id' => $classroom->id]))->assertOk()->assertDownload();
        }
        $this->actingAs($outsider)->get(route('workspace.export', [$outsider->currentTeam->slug, 'learning-attendance', 'csv', 'classroom_id' => $classroom->id]))->assertForbidden();
    }

    public function test_attendance_amendments_require_attribution_and_invalid_rosters_or_future_sessions_are_rejected(): void
    {
        [$classroom, $facilitator, $enrollment] = $this->classroomWithRoster();
        $manual = [...$this->presentPayload($classroom, $enrollment), 'source' => 'manual', 'provider_event_id' => null];
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $manual)->assertRedirect();

        $absent = ['learning_enrollment_id' => $enrollment->id, 'attendance_status' => 'absent', 'source' => 'manual'];
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), $absent)->assertStatus(422);
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), [...$absent, 'notes' => 'Provider log confirmed that the learner did not join.'])->assertRedirect();
        $attendance = VirtualClassroomAttendance::query()->sole();
        $this->assertSame('absent', $attendance->attendance_status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $attendance->id, 'action' => 'learning.classroom_attendance_amended']);

        $otherCourse = LearningCourse::factory()->create(['status' => 'published']);
        $otherEnrollment = LearningEnrollment::factory()->create(['learning_course_id' => $otherCourse->id]);
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $classroom]), ['learning_enrollment_id' => $otherEnrollment->id, 'attendance_status' => 'absent', 'source' => 'manual'])->assertStatus(409);

        $futureClassroom = VirtualClassroom::factory()->create(['learning_course_id' => $classroom->learning_course_id, 'facilitator_id' => $facilitator->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);
        $this->actingAs($facilitator)->post(route('learning.classrooms.attendance.store', [$facilitator->currentTeam->slug, $futureClassroom]), ['learning_enrollment_id' => $enrollment->id, 'attendance_status' => 'absent', 'source' => 'manual'])->assertStatus(409);
    }

    /** @return array{VirtualClassroom, User, LearningEnrollment, LearningEnrollment} */
    private function classroomWithRoster(?int $capacity = 10): array
    {
        $county = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $facilitator = User::factory()->topManagement()->create();
        $course = LearningCourse::factory()->create(['county_id' => $county->id, 'owner_id' => $manager->id, 'created_by' => $manager->id, 'status' => 'published']);
        $firstLearner = User::factory()->countyOfficial($county)->create();
        $secondLearner = User::factory()->countyOfficial($county)->create();
        $firstEnrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $firstLearner->id, 'county_id' => $county->id, 'enrolled_by' => $firstLearner->id]);
        $secondEnrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $secondLearner->id, 'county_id' => $county->id, 'enrolled_by' => $secondLearner->id]);
        $classroom = VirtualClassroom::factory()->create(['learning_course_id' => $course->id, 'facilitator_id' => $facilitator->id, 'created_by' => $manager->id, 'starts_at' => now()->subHours(2), 'ends_at' => now(), 'capacity' => $capacity, 'status' => 'completed']);

        return [$classroom, $facilitator, $firstEnrollment->load('user', 'county'), $secondEnrollment->load('user', 'county')];
    }

    /** @return array<string, mixed> */
    private function presentPayload(VirtualClassroom $classroom, LearningEnrollment $enrollment, ?string $providerEventId = null): array
    {
        return ['learning_enrollment_id' => $enrollment->id, 'attendance_status' => 'present', 'joined_at' => $classroom->starts_at->toIso8601String(), 'left_at' => $classroom->starts_at->addMinutes(90)->toIso8601String(), 'source' => $providerEventId === null ? 'manual' : 'provider_import', 'provider_event_id' => $providerEventId];
    }
}
