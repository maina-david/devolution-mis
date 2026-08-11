<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningModule;
use App\Models\LearningOfflinePackage;
use App\Models\LearningQuizQuestion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class LearningOfflinePackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_manager_generates_checksum_bound_answer_safe_immutable_offline_package(): void
    {
        [$course, $manager, , $manual, $document] = $this->publishedCourseWithDownloadableAsset();

        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertRedirect()->assertSessionHasNoErrors();

        $package = LearningOfflinePackage::query()->sole();
        $this->assertTrue(Str::isUuid($package->id));
        $this->assertSame('ready', $package->status);
        $this->assertSame(1, $package->package_version);
        $this->assertSame(64, strlen((string) $package->content_checksum));
        $this->assertSame(64, strlen((string) $package->manifest_checksum));
        $this->assertEquals(['modules' => 1, 'lessons' => 3, 'assets' => 1, 'sync_schema' => 'idmis.learning-offline-progress.v1'], $package->manifest_summary);
        Storage::disk('local')->assertExists((string) $package->path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path((string) $package->path)) === true);
        $manifest = (string) $zip->getFromName('manifest.json');
        $index = (string) $zip->getFromName('index.html');
        $progressTemplate = (string) $zip->getFromName('offline-progress-template.json');
        $this->assertStringContainsString('idmis.learning-offline-package.v1', $manifest);
        $this->assertStringContainsString($document->content_checksum, $manifest);
        $this->assertStringContainsString('assets/'.Str::slug($manual->title), $manifest);
        $this->assertStringNotContainsString('correct_option', $manifest);
        $this->assertStringContainsString('idmis.learning-offline-progress.v1', $manifest);
        $this->assertStringContainsString('does not alter the official learning record', $index);
        $this->assertStringContainsString('Export progress record', $index);
        $this->assertStringContainsString('crypto.randomUUID()', $index);
        $this->assertStringContainsString($package->id, $progressTemplate);
        $this->assertStringContainsString($package->manifest_checksum, $progressTemplate);
        $this->assertNotFalse($zip->locateName('assets/'.Str::slug($manual->title).'/field-manual.pdf'));
        $zip->close();
        $this->assertDatabaseHas('audit_events', ['subject_id' => $package->id, 'action' => 'learning.offline-package.generated']);

        $this->expectException(QueryException::class);
        $package->update(['status' => 'failed']);
    }

    public function test_only_enrolled_in_scope_learner_or_governance_user_can_download_and_tamper_fails_closed(): void
    {
        [$course, $manager, $county] = $this->publishedCourseWithDownloadableAsset();
        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertRedirect();
        $package = LearningOfflinePackage::query()->sole();
        $learner = User::factory()->countyOfficial($county)->create();
        $otherLearner = User::factory()->countyOfficial(County::factory()->create())->create();

        $this->actingAs($learner)->get(route('learning.offline-packages.download', [$learner->currentTeam->slug, $package]))->assertForbidden();
        LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $learner->id, 'enrolled_by' => $learner->id]);
        LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $otherLearner->id, 'enrolled_by' => $otherLearner->id]);
        $this->actingAs($learner)->get(route('learning.offline-packages.download', [$learner->currentTeam->slug, $package]))->assertOk()->assertDownload($package->original_name);
        $this->actingAs($otherLearner)->get(route('learning.offline-packages.download', [$otherLearner->currentTeam->slug, $package]))->assertForbidden();

        Storage::disk('local')->put((string) $package->path, 'tampered archive');
        $this->actingAs($manager)->get(route('learning.offline-packages.download', [$manager->currentTeam->slug, $package]))->assertStatus(409);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $package->id, 'action' => 'learning.offline-package.downloaded', 'actor_id' => $learner->id]);
    }

    public function test_generation_rejects_drafts_and_retains_failed_integrity_evidence(): void
    {
        [$course, $manager, , , $document] = $this->publishedCourseWithDownloadableAsset();
        $course->update(['status' => 'draft']);
        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertStatus(409);
        $this->assertDatabaseCount('learning_offline_packages', 0);

        $course->update(['status' => 'published']);
        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertRedirect();
        $readyPackage = LearningOfflinePackage::query()->where('status', 'ready')->sole();
        Storage::disk('local')->put($document->currentVersion->path, 'altered evidence');
        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertSessionHasErrors('package');
        $package = LearningOfflinePackage::query()->where('status', 'failed')->sole();
        $this->assertSame(2, $package->package_version);
        $this->assertSame('failed', $package->status);
        $this->assertNotNull($package->failed_at);
        $this->assertStringContainsString('integrity verification', (string) $package->failure_message);
        $this->actingAs($manager)->get(route('learning.index', $manager->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page
            ->where('courses.data.0.offlinePackage.id', $readyPackage->id)
            ->where('courses.data.0.offlinePackageAttempt.version', 2)
            ->where('courses.data.0.offlinePackageAttempt.status', 'failed'));
    }

    public function test_learning_workspace_and_exports_expose_latest_package_evidence(): void
    {
        [$course, $manager] = $this->publishedCourseWithDownloadableAsset();
        $this->actingAs($manager)->post(route('learning.courses.offline-packages.store', [$manager->currentTeam->slug, $course]))->assertRedirect();
        $package = LearningOfflinePackage::query()->sole();

        $this->actingAs($manager)->get(route('learning.index', $manager->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page
            ->where('courses.data.0.offlinePackage.id', $package->id)
            ->where('courses.data.0.offlinePackage.version', 1)
            ->where('courses.data.0.offlinePackage.canDownload', true));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($manager)->get(route('workspace.export', [$manager->currentTeam->slug, 'learning', $format]))->assertOk()->assertDownload();
        }
    }

    /** @return array{LearningCourse, User, County, LearningLesson, AssessmentDocument} */
    private function publishedCourseWithDownloadableAsset(): array
    {
        $county = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $course = LearningCourse::factory()->create(['owner_id' => $manager->id, 'created_by' => $manager->id, 'county_id' => $county->id, 'status' => 'published', 'code' => 'OFFLINE-101', 'title' => 'Offline Devolution Practice']);
        $module = LearningModule::factory()->create(['learning_course_id' => $course->id, 'title' => 'Core offline module']);
        LearningLesson::factory()->create(['learning_module_id' => $module->id, 'title' => 'Core principles', 'content_type' => 'text', 'content_body' => 'Accountable service delivery remains the governing principle.', 'sequence' => 1]);
        $manual = LearningLesson::factory()->create(['learning_module_id' => $module->id, 'title' => 'Field manual', 'content_type' => 'manual', 'content_body' => null, 'is_downloadable' => true, 'sequence' => 2, 'metadata' => ['licence' => 'permission_granted', 'accessible_alternative' => 'Complete structured text alternative for the field manual.']]);
        $quiz = LearningLesson::factory()->create(['learning_module_id' => $module->id, 'title' => 'Knowledge check', 'content_type' => 'quiz', 'content_body' => null, 'sequence' => 3]);
        LearningQuizQuestion::factory()->create(['learning_lesson_id' => $quiz->id, 'question' => 'What is required?', 'options' => ['A' => 'Accountability', 'B' => 'Correct answer'], 'correct_option' => 'B']);
        $contents = "%PDF-1.4\nfield manual\n%%EOF";
        $path = "learning-assets/{$manual->id}.pdf";
        Storage::disk('local')->put($path, $contents);
        $document = AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $county->id, 'path' => $path, 'original_name' => 'field-manual.pdf', 'content_checksum' => hash('sha256', $contents), 'scan_status' => 'clean', 'record_status' => 'active']);
        $version = DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'uploaded_by' => $manager->id, 'storage_disk' => 'local', 'path' => $path, 'original_name' => 'field-manual.pdf', 'size_bytes' => strlen($contents), 'content_checksum' => hash('sha256', $contents), 'scan_status' => 'clean']);
        $document->update(['current_version_id' => $version->id]);
        DocumentLink::create(['assessment_document_id' => $document->id, 'subject_type' => $manual->getMorphClass(), 'subject_id' => $manual->id, 'purpose' => 'learning-lesson-asset', 'created_by' => $manager->id]);

        return [$course, $manager, $county, $manual, $document->refresh()->load('currentVersion')];
    }
}
