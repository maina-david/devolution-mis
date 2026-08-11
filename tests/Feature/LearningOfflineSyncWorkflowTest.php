<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningModule;
use App\Models\LearningOfflinePackage;
use App\Models\LearningOfflineSync;
use App\Models\LearningProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningOfflineSyncWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_submits_checksum_bound_activity_and_independent_reviewer_applies_progress(): void
    {
        [$county, $learner, $reviewer, $enrollment, $lesson, $package] = $this->scenario();
        $payload = $this->payload($package, $lesson);

        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($payload)])->assertRedirect()->assertSessionHasNoErrors();

        $sync = LearningOfflineSync::query()->sole();
        $this->assertTrue(Str::isUuid($sync->id));
        $this->assertSame('7', $sync->id[14]);
        $this->assertSame('pending', $sync->status);
        $this->assertSame($county->id, $sync->county_id);
        $this->assertSame($package->manifest_checksum, $sync->payload['package_manifest_checksum']);
        $this->assertSame(64, mb_strlen($sync->payload_checksum));
        $this->assertSame(64, mb_strlen($sync->base_progress_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $sync->id, 'action' => 'learning.offline-sync.submitted']);
        $this->assertDatabaseCount('learning_progress', 0);

        $this->actingAs($reviewer)->post(route('learning.offline-syncs.decision.store', [$reviewer->currentTeam->slug, $sync]), ['decision' => 'approve', 'rationale' => 'The package checksum, event chronology and learner enrolment were independently reconciled.'])->assertRedirect()->assertSessionHasNoErrors();

        $sync->refresh();
        $progress = LearningProgress::query()->sole();
        $this->assertSame('approved', $sync->status);
        $this->assertSame($reviewer->id, $sync->reviewed_by);
        $this->assertNotNull($sync->applied_at);
        $this->assertSame(64, mb_strlen((string) $sync->decision_checksum));
        $this->assertSame('completed', $progress->status);
        $this->assertSame('100.00', $progress->progress_percentage);
        $this->assertSame($sync->id, $progress->state['_offline_sync']['sync_id']);
        $this->assertSame('100.00', $enrollment->refresh()->progress_percentage);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $sync->id, 'action' => 'learning.offline-sync.approved']);

        $this->expectException(QueryException::class);
        $sync->update(['decision_reason' => 'Attempted mutation of terminal reconciliation evidence.']);
    }

    public function test_exact_replay_is_idempotent_but_collision_tamper_and_self_review_fail_closed(): void
    {
        [, $learner, , $enrollment, $lesson, $package] = $this->scenario();
        $payload = $this->payload($package, $lesson);

        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($payload)])->assertRedirect();
        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($payload)])->assertRedirect();
        $this->assertDatabaseCount('learning_offline_syncs', 1);

        $collision = $payload;
        $collision['events'][0]['time_spent_seconds'] = 999;
        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($collision)])->assertStatus(409);
        $tampered = $payload;
        $tampered['client_sync_id'] = (string) Str::uuid();
        $tampered['package_manifest_checksum'] = str_repeat('0', 64);
        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($tampered)])->assertStatus(409);

        $sync = LearningOfflineSync::query()->sole();
        $learner->givePermissionTo(ProgrammePermission::ReviewLearning->value);
        $this->actingAs($learner)->post(route('learning.offline-syncs.decision.store', [$learner->currentTeam->slug, $sync]), ['decision' => 'approve', 'rationale' => 'A learner must never approve their own synchronized learning activity.'])->assertForbidden();
        $this->assertSame('pending', $sync->refresh()->status);
        $this->assertDatabaseCount('learning_progress', 0);
    }

    public function test_newer_official_progress_creates_immutable_conflict_without_regression(): void
    {
        [, $learner, $reviewer, $enrollment, $lesson, $package] = $this->scenario();
        $payload = $this->payload($package, $lesson, 50, 'in_progress');
        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($payload)])->assertRedirect();
        $sync = LearningOfflineSync::query()->sole();

        $this->actingAs($learner)->patch(route('learning.lessons.complete', [$learner->currentTeam->slug, $enrollment, $lesson]), ['time_spent_seconds' => 1800, 'state' => ['source' => 'online']])->assertRedirect();
        $this->actingAs($reviewer)->post(route('learning.offline-syncs.decision.store', [$reviewer->currentTeam->slug, $sync]), ['decision' => 'approve', 'rationale' => 'Reconcile the retained offline payload against the current official progress snapshot.'])->assertRedirect();

        $this->assertSame('conflict', $sync->refresh()->status);
        $progress = LearningProgress::query()->sole();
        $this->assertSame('100.00', $progress->progress_percentage);
        $this->assertSame('online', $progress->state['source']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $sync->id, 'action' => 'learning.offline-sync.conflict']);
    }

    public function test_workspace_scope_pagination_and_all_four_exports_expose_reconciliation_evidence(): void
    {
        [$county, $learner, $reviewer, $enrollment, $lesson, $package] = $this->scenario();
        $otherCounty = County::factory()->create();
        $otherLearner = User::factory()->countyOfficial($otherCounty)->create();
        $otherCourse = LearningCourse::factory()->create(['county_id' => $otherCounty->id, 'status' => 'published']);
        $otherEnrollment = LearningEnrollment::factory()->create(['learning_course_id' => $otherCourse->id, 'user_id' => $otherLearner->id, 'county_id' => $otherCounty->id, 'enrolled_by' => $otherLearner->id]);
        $otherLesson = LearningLesson::factory()->create(['learning_module_id' => LearningModule::factory()->create(['learning_course_id' => $otherCourse->id])->id, 'content_type' => 'text']);
        $otherPackage = LearningOfflinePackage::factory()->create(['learning_course_id' => $otherCourse->id, 'generated_at' => now()->subHour()]);

        $this->actingAs($learner)->post(route('learning.enrollments.offline-syncs.store', [$learner->currentTeam->slug, $enrollment]), ['sync_file' => $this->jsonFile($this->payload($package, $lesson))])->assertRedirect();
        $this->actingAs($otherLearner)->post(route('learning.enrollments.offline-syncs.store', [$otherLearner->currentTeam->slug, $otherEnrollment]), ['sync_file' => $this->jsonFile($this->payload($otherPackage, $otherLesson))])->assertRedirect();
        $ownSync = LearningOfflineSync::query()->where('submitted_by', $learner->id)->sole();

        $this->actingAs($learner)->get(route('learning.index', [$learner->currentTeam->slug, 'per_page' => 10]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('offlineSyncs.total', 1)
            ->where('offlineSyncs.data.0.id', $ownSync->id)
            ->where('offlineSyncs.data.0.county.id', $county->id));
        $this->actingAs($reviewer)->get(route('learning.index', [$reviewer->currentTeam->slug, 'per_page' => 10]))->assertOk()->assertInertia(fn ($page) => $page->where('offlineSyncs.total', 2));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($learner)->get(route('workspace.export', [$learner->currentTeam->slug, 'learning-offline-syncs', $format]))->assertOk()->assertDownload();
        }
    }

    /** @return array{County, User, User, LearningEnrollment, LearningLesson, LearningOfflinePackage} */
    private function scenario(): array
    {
        $county = County::factory()->create();
        $learner = User::factory()->countyOfficial($county)->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $course = LearningCourse::factory()->create(['county_id' => $county->id, 'status' => 'published']);
        $module = LearningModule::factory()->create(['learning_course_id' => $course->id]);
        $lesson = LearningLesson::factory()->create(['learning_module_id' => $module->id, 'content_type' => 'text', 'is_required' => true]);
        $enrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $learner->id, 'county_id' => $county->id, 'enrolled_by' => $learner->id]);
        $package = LearningOfflinePackage::factory()->create(['learning_course_id' => $course->id, 'generated_at' => now()->subHour()]);

        return [$county, $learner, $reviewer, $enrollment, $lesson, $package];
    }

    /** @return array<string, mixed> */
    private function payload(LearningOfflinePackage $package, LearningLesson $lesson, int $progress = 100, string $status = 'completed'): array
    {
        return [
            'schema' => 'idmis.learning-offline-progress.v1',
            'client_sync_id' => (string) Str::uuid(),
            'device_id' => (string) Str::uuid(),
            'package_id' => $package->id,
            'package_manifest_checksum' => $package->manifest_checksum,
            'exported_at' => now()->toIso8601String(),
            'events' => [[
                'client_event_id' => (string) Str::uuid(),
                'lesson_id' => $lesson->id,
                'status' => $status,
                'progress_percentage' => $progress,
                'time_spent_seconds' => 1200,
                'occurred_at' => now()->subMinutes(5)->toIso8601String(),
                'state' => ['position' => 'complete'],
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function jsonFile(array $payload): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('offline-progress.json', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
