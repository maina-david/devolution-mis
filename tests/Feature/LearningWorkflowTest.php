<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Database\Seeders\LearningSeeder;
use Database\Seeders\LearningWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_local_learning_seed_creates_required_governed_asset_before_publication(): void
    {
        User::factory()->devolutionAdmin()->create(['email' => 'devolution.admin@idmis.test']);
        User::factory()->topManagement()->create(['email' => 'management@idmis.test']);
        User::factory()->countyOfficial(County::factory()->create())->create(['email' => 'county.official@idmis.test']);
        $this->seed(LearningWorkflowSeeder::class);
        $this->publishedReferenceRelease([], $this->user('devolution.admin@idmis.test'));
        app()->detectEnvironment(fn (): string => 'local');

        try {
            $this->seed(LearningSeeder::class);
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }

        $course = LearningCourse::query()->with('modules.lessons.documentLinks.document')->sole();
        $manual = $course->modules->flatMap->lessons->firstWhere('content_type', 'manual');
        $this->assertSame('published', $course->status);
        $this->assertInstanceOf(LearningLesson::class, $manual);
        $this->assertSame('clean', $manual->documentLinks->sole()->document->scan_status);
        $this->assertNotEmpty($manual->assetMetadata()['accessible_alternative']);
        $this->assertSame('permission_granted', $manual->assetMetadata()['licence']);
    }

    public function test_multimedia_course_is_independently_published_completed_assessed_and_certified(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->topManagement()->create();
        $learner = User::factory()->countyOfficial(County::factory()->create())->create();
        $this->seed(LearningWorkflowSeeder::class);
        $release = $this->publishedReferenceRelease([], $author);
        $this->actingAs($author)->post(route('learning.courses.store'), $this->coursePayload())->assertRedirect();
        $course = LearningCourse::query()->with('modules.lessons.questions')->sole();
        $this->assertSame($release->id, $course->reference_data_release_id);
        $this->assertTrue(Str::isUuid($course->id));
        $this->assertSame(4, $course->modules->flatMap->lessons->count());
        $this->assertSame(['manual', 'quiz', 'text', 'video'], $course->modules->flatMap->lessons->pluck('content_type')->sort()->values()->all());
        $this->uploadRequiredAssets($course, $author);
        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'submit_review', 'rationale' => 'Multimedia content and assessment submitted for quality review.'])->assertRedirect();
        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'publish', 'rationale' => 'Attempted self-publication.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('learning.courses.transition', [$course]), ['transition' => 'publish', 'rationale' => 'Content, accessibility metadata and assessment independently reviewed.'])->assertRedirect();
        $this->assertSame('published', $course->refresh()->status);
        $this->actingAs($author)->post(route('learning.classrooms.store'), ['learning_course_id' => $course->id, 'facilitator_id' => $reviewer->id, 'title' => 'Live implementation workshop', 'description' => 'Facilitated application workshop.', 'starts_at' => now()->addWeek()->toIso8601String(), 'ends_at' => now()->addWeek()->addHours(2)->toIso8601String(), 'platform' => 'Microsoft Teams', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/test', 'capacity' => 100, 'status' => 'scheduled'])->assertRedirect();
        $this->assertDatabaseHas('virtual_classrooms', ['learning_course_id' => $course->id, 'status' => 'scheduled']);
        $this->actingAs($learner)->post(route('learning.enrollments.store'), ['learning_course_id' => $course->id])->assertRedirect();
        $enrollment = LearningEnrollment::query()->sole();
        $videoAsset = $course->modules->flatMap->lessons->firstWhere('content_type', 'video')->documentLinks()->firstOrFail()->document;
        $this->actingAs($learner)->get(route('evidence.preview', [$videoAsset]))
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($learner)->withHeader('Range', 'bytes=0-9')->get(route('evidence.preview', [$videoAsset]))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-9/1024')
            ->assertHeader('Content-Length', '10');
        $this->withoutHeader('Range');
        foreach ($course->modules->flatMap->lessons->where('content_type', '!=', 'quiz') as $lesson) {
            $this->actingAs($learner)->patch(route('learning.lessons.complete', [$enrollment, $lesson]), ['time_spent_seconds' => 600, 'state' => ['position' => 'complete']])->assertRedirect();
        }$question = $course->modules->flatMap->lessons->flatMap->questions->sole();
        $this->actingAs($learner)->post(route('learning.assessments.store', [$enrollment]), ['answers' => [$question->id => 'B']])->assertRedirect();
        $this->assertSame('0.00', $enrollment->refresh()->best_score);
        $this->actingAs($learner)->post(route('learning.assessments.store', [$enrollment]), ['answers' => [$question->id => 'A']])->assertRedirect();
        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertSame('100.00', $enrollment->progress_percentage);
        $this->assertSame('100.00', $enrollment->best_score);
        $certificate = LearningCertificate::query()->sole();
        $this->assertSame(64, strlen($certificate->content_checksum));
        $this->actingAs($learner)->get(route('learning.certificates.show', [$certificate]))->assertOk()->assertHeader('content-type', 'application/pdf');
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($learner)->get(route('workspace.export', ['learning', $format]))->assertOk()->assertDownload();
        }
        $csv = $this->actingAs($learner)->get(route('workspace.export', ['learning', 'csv']));
        $csvContents = $csv->streamedContent();
        $this->assertStringContainsString('Reference release', $csvContents);
        $this->assertStringContainsString($release->checksum, $csvContents);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $enrollment->id, 'action' => 'learning.assessment.submitted']);
    }

    public function test_course_creation_stops_when_the_governed_catalogue_is_missing_or_corrupt(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $this->seed(LearningWorkflowSeeder::class);

        $this->actingAs($author)
            ->post(route('learning.courses.store'), $this->coursePayload())
            ->assertStatus(409);
        $this->assertDatabaseCount('learning_courses', 0);
        $this->actingAs($author)->get(route('learning.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('catalogue.available', false));

        $this->publishedReferenceRelease([], $author, str_repeat('0', 64));

        $this->actingAs($author)
            ->post(route('learning.courses.store'), $this->coursePayload())
            ->assertStatus(409);
        $this->assertDatabaseCount('learning_courses', 0);
    }

    public function test_required_content_and_attempt_limits_are_enforced(): void
    {
        [$course,$learner] = $this->publishedCourse();
        $this->actingAs($learner)->post(route('learning.enrollments.store'), ['learning_course_id' => $course->id])->assertRedirect();
        $enrollment = LearningEnrollment::query()->sole();
        $question = $course->modules->flatMap->lessons->flatMap->questions->sole();
        $this->actingAs($learner)->post(route('learning.assessments.store', [$enrollment]), ['answers' => [$question->id => 'A']])->assertSessionHasErrors('answers');
        $this->assertDatabaseCount('learning_assessment_attempts', 0);
    }

    public function test_county_targeting_and_private_learning_records_are_scoped(): void
    {
        $target = County::factory()->create();
        $other = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->topManagement()->create();
        $targetLearner = User::factory()->countyOfficial($target)->create();
        $otherLearner = User::factory()->countyOfficial($other)->create();
        $this->seed(LearningWorkflowSeeder::class);
        $release = $this->publishedReferenceRelease([$target], $author);
        $payload = $this->coursePayload();
        $payload['county_id'] = $target->id;
        $this->actingAs($author)->post(route('learning.courses.store'), $payload)->assertRedirect();
        $course = LearningCourse::query()->with('modules.lessons')->sole();
        $this->assertSame($release->id, $course->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $course->id, 'action' => 'learning.course.created']);
        $this->uploadRequiredAssets($course, $author);
        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'submit_review', 'rationale' => 'Submit county-targeted course.'])->assertRedirect();
        $this->actingAs($reviewer)->patch(route('learning.courses.transition', [$course]), ['transition' => 'publish', 'rationale' => 'Independently approved.'])->assertRedirect();
        $this->actingAs($targetLearner)->post(route('learning.enrollments.store'), ['learning_course_id' => $course->id])->assertRedirect();
        $this->actingAs($otherLearner)->post(route('learning.enrollments.store'), ['learning_course_id' => $course->id])->assertForbidden();
        $asset = $course->modules->flatMap->lessons->firstWhere('content_type', 'manual')->documentLinks()->firstOrFail()->document;
        $this->actingAs($otherLearner)->get(route('evidence.preview', [$asset]))->assertForbidden();
        $this->actingAs($otherLearner)->get(route('learning.index'))->assertOk()->assertInertia(fn ($page) => $page->where('courses.total', 0));
    }

    public function test_quality_review_requires_accessible_repository_assets_and_locks_them_after_submission(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $this->seed(LearningWorkflowSeeder::class);
        $this->publishedReferenceRelease([], $author);
        $this->actingAs($author)->post(route('learning.courses.store'), $this->coursePayload())->assertRedirect();
        $course = LearningCourse::query()->with('modules.lessons')->sole();
        $video = $course->modules->flatMap->lessons->firstWhere('content_type', 'video');

        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'submit_review', 'rationale' => 'Attempt without governed assets.'])->assertStatus(409);
        $invalid = $this->assetPayload($video, false);
        $this->withSession(['locale' => 'fr'])
            ->actingAs($author)
            ->post(route('learning.lessons.assets.store', [$course, $video]), $invalid)
            ->assertStatus(422)
            ->assertSee('Les leçons vidéo nécessitent une vidéo numérique et une transcription.');
        $this->assertDatabaseCount('assessment_documents', 0);

        $this->uploadRequiredAssets($course, $author);
        $video->refresh();
        $this->assertNull($video->content_url);
        $this->assertSame('permission_granted', $video->metadata['licence']);
        $this->assertTrue($video->metadata['transcript_available']);
        $this->assertSame(64, strlen($video->content_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $video->id, 'action' => 'learning.lesson_asset_registered']);
        $this->assertSame(
            'Ressource gouvernée enregistrée pour la leçon '.$video->title.'.',
            AuditEvent::query()->where('subject_id', $video->id)->where('action', 'learning.lesson_asset_registered')->sole()->description,
        );

        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'submit_review', 'rationale' => 'Accessible repository assets are complete.'])->assertRedirect();
        $this->actingAs($author)
            ->post(route('learning.lessons.assets.store', [$course, $video]), $this->assetPayload($video, true))
            ->assertStatus(409)
            ->assertSee('Les ressources de formation sont verrouillées dès le début du contrôle qualité.');
    }

    /** @return array{LearningCourse,User} */
    private function publishedCourse(): array
    {
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->topManagement()->create();
        $learner = User::factory()->countyOfficial(County::factory()->create())->create();
        $this->seed(LearningWorkflowSeeder::class);
        $this->publishedReferenceRelease([], $author);
        $this->actingAs($author)->post(route('learning.courses.store'), $this->coursePayload())->assertRedirect();
        $course = LearningCourse::query()->with('modules.lessons')->sole();
        $this->uploadRequiredAssets($course, $author);
        $this->actingAs($author)->patch(route('learning.courses.transition', [$course]), ['transition' => 'submit_review', 'rationale' => 'Quality review requested.'])->assertRedirect();
        $this->actingAs($reviewer)->patch(route('learning.courses.transition', [$course]), ['transition' => 'publish', 'rationale' => 'Quality review passed.'])->assertRedirect();

        return [$course->load('modules.lessons.questions'), $learner];
    }

    /** @return array<string,mixed> */
    private function coursePayload(): array
    {
        return ['code' => 'DEV-FOUND-101', 'title' => 'Foundations of Devolution Delivery', 'summary' => 'A practical introduction to accountable devolved service delivery.', 'description' => 'Build a shared understanding of constitutional devolution, evidence standards, citizen accountability and results-oriented delivery.', 'category' => 'Devolution foundations', 'level' => 'foundation', 'delivery_mode' => 'blended', 'language' => 'en', 'passing_score' => 70, 'maximum_attempts' => 3, 'modules' => [['title' => 'Core learning', 'description' => 'Multimedia learning and operational resources.', 'lessons' => [['title' => 'Constitutional foundations', 'summary' => 'Read the core principles.', 'content_type' => 'text', 'content_body' => 'Devolution distributes functions and resources while strengthening accountable service delivery.', 'estimated_minutes' => 20, 'is_downloadable' => false], ['title' => 'County delivery briefing', 'summary' => 'Watch the implementation briefing.', 'content_type' => 'video', 'content_url' => 'https://learning.example.test/video/devolution-foundations', 'estimated_minutes' => 15, 'is_downloadable' => false], ['title' => 'Implementation toolkit', 'summary' => 'Download the practical toolkit.', 'content_type' => 'manual', 'content_url' => 'https://learning.example.test/toolkits/devolution-delivery.pdf', 'estimated_minutes' => 25, 'is_downloadable' => true], ['title' => 'Knowledge check', 'summary' => 'Complete the course assessment.', 'content_type' => 'quiz', 'estimated_minutes' => 10, 'is_downloadable' => false, 'questions' => [['question' => 'Which principle is central to accountable devolution?', 'options' => ['A' => 'Transparent, citizen-focused service delivery', 'B' => 'Unattributed decisions'], 'correct_option' => 'A', 'explanation' => 'Accountability and citizen-centred delivery are core devolution principles.', 'points' => 1]]]]]]];
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver, ?string $checksum = null): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => $checksum ?? app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-LEARNING-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function uploadRequiredAssets(LearningCourse $course, User $author): void
    {
        foreach ($course->modules->flatMap->lessons->whereIn('content_type', ['video', 'audio', 'toolkit', 'manual']) as $lesson) {
            $this->actingAs($author)->post(route('learning.lessons.assets.store', [$course, $lesson]), $this->assetPayload($lesson, true))->assertSessionHasNoErrors()->assertRedirect();
            $document = $lesson->documentLinks()->sole()->document;
            Storage::disk('local')->assertExists($document->path);
        }
    }

    /** @return array<string, mixed> */
    private function assetPayload(LearningLesson $lesson, bool $transcript): array
    {
        $isMedia = in_array($lesson->content_type, ['video', 'audio'], true);
        $extension = $lesson->content_type === 'video' ? 'mp4' : ($lesson->content_type === 'audio' ? 'mp3' : 'pdf');
        $content = match ($extension) {
            'mp4' => "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 1000),
            'mp3' => 'ID3'.str_repeat("\x00", 1021),
            default => "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n".str_repeat(' ', 980)."\n%%EOF",
        };

        return ['title' => $lesson->title.' governed asset', 'source_type' => $isMedia ? 'digital' : 'scanned', 'rights_holder' => 'State Department for Devolution', 'licence' => 'permission_granted', 'accessible_alternative' => 'Structured text alternative describing the complete lesson content and learning objective.', 'transcript_available' => $transcript, 'is_downloadable' => ! $isMedia, 'document' => UploadedFile::fake()->createWithContent(str($lesson->title)->slug().'.'.$extension, $content)];
    }
}
