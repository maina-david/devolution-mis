<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\KnowledgeItem;
use App\Models\LearningAssessmentAttempt;
use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningModule;
use App\Models\LearningQuestionBank;
use App\Models\LearningQuestionBankItem;
use App\Models\LearningQuizQuestion;
use App\Models\User;
use App\Services\LearningAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class LearningAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('analytics.minimum_aggregate_cell_size', 2);
    }

    public function test_learning_metrics_are_calculated_only_inside_the_authorized_county_scope(): void
    {
        $home = County::factory()->create(['code' => 1, 'name' => 'Mombasa']);
        $other = County::factory()->create(['code' => 2, 'name' => 'Kwale']);
        $course = LearningCourse::factory()->create(['code' => 'LRN-DEV-101', 'title' => 'County devolution foundations', 'status' => 'published']);
        $homeAdmin = User::factory()->countyAdmin($home)->create();
        $national = User::factory()->devolutionAdmin()->create();

        $completed = $this->enrollment($course, $home, 'completed', 100, 84);
        $this->enrollment($course, $home, 'in_progress', 50, 60);
        $this->enrollment($course, $other, 'completed', 100, 96);
        LearningCertificate::factory()->create(['learning_enrollment_id' => $completed->id]);

        $this->actingAs($homeAdmin)->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('learning/analytics')
                ->where('report.summary.enrollments', 2)
                ->where('report.summary.active', 1)
                ->where('report.summary.completed', 1)
                ->where('report.summary.completionRate', 50)
                ->where('report.summary.certificates', 1)
                ->where('report.summary.averageScore', 72)
                ->where('report.courses.rows.0.code', 'LRN-DEV-101')
                ->where('report.counties.rows.0.county.name', 'Mombasa')
                ->has('report.counties.rows', 1)
            );

        $this->actingAs($homeAdmin)->get(route('learning.analytics.index', ['county_id' => $other->id]))->assertForbidden();

        $this->actingAs($national)->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.enrollments', 3)
                ->where('report.summary.completed', 2)
                ->has('report.counties.rows', 2)
            );
    }

    public function test_learning_analytics_filters_exports_and_audit_are_governed(): void
    {
        $county = County::factory()->create();
        $course = LearningCourse::factory()->create(['code' => 'LRN-ME-201', 'title' => 'Results-based monitoring', 'status' => 'published']);
        $otherCourse = LearningCourse::factory()->create(['status' => 'published']);
        $admin = User::factory()->devolutionAdmin()->create();
        $this->enrollment($course, $county, 'completed', 100, 90, '2026-07-12');
        $this->enrollment($otherCourse, $county, 'in_progress', 25, null, '2026-08-01');

        $filters = ['course_id' => $course->id, 'from' => '2026-07-01', 'to' => '2026-07-31', 'status' => 'completed'];
        $this->actingAs($admin)->get(route('learning.analytics.index', [...$filters]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.suppressed', true)
                ->where('report.summary.enrollments', null)
                ->where('report.courses.rows.0.code', 'LRN-ME-201')
                ->where('filters.course_id', $course->id)
            );

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($admin)->get(route('learning.analytics.export', [$format, ...$filters]))->assertOk()->assertDownload();
        }
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'action' => 'learning.analytics.exported']);
        auth()->logout();
        $this->get(route('learning.analytics.index'))->assertRedirect(route('login'));
    }

    public function test_small_cells_are_suppressed_in_the_page_and_every_export_source(): void
    {
        config()->set('analytics.minimum_aggregate_cell_size', 5);
        $county = County::factory()->create(['code' => 14, 'name' => 'Embu']);
        $course = LearningCourse::factory()->create(['code' => 'LRN-PRIV-401', 'title' => 'Privacy-aware reporting', 'status' => 'published']);
        $admin = User::factory()->devolutionAdmin()->create();

        foreach ([97.25, 92.5, 88.75] as $score) {
            $this->enrollment($course, $county, 'completed', 100, $score);
        }

        $this->actingAs($admin)->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.privacy.minimumCellSize', 5)
                ->where('report.summary.hasData', true)
                ->where('report.summary.suppressed', true)
                ->where('report.summary.enrollments', null)
                ->where('report.summary.averageScore', null)
                ->where('report.courses.rows.0.suppressed', true)
                ->where('report.courses.rows.0.completed', null)
                ->where('report.counties.rows.0.suppressed', true)
                ->where('report.trend.0.suppressed', true)
                ->where('report.trend.0.enrollments', null)
            );

        $rows = app(LearningAnalyticsService::class)->exportRows($admin, []);
        $this->assertTrue($rows[0]['suppressed']);
        $this->assertNull($rows[0]['enrollments']);
        $this->assertNull($rows[0]['average_score']);

        $csv = $this->actingAs($admin)->get(route('learning.analytics.export', ['csv']));
        $csv->assertOk()->assertDownload();
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Suppressed (<5)', $csvContent);
        $this->assertStringNotContainsString('97.25', $csvContent);

        $json = $this->actingAs($admin)->get(route('learning.analytics.export', ['json']));
        $json->assertOk()->assertDownload();
        $jsonContent = $json->streamedContent();
        $this->assertStringContainsString('"suppressed": true', $jsonContent);
        $this->assertStringNotContainsString('97.25', $jsonContent);

        $xlsx = $this->actingAs($admin)->get(route('learning.analytics.export', ['xlsx']));
        $xlsx->assertOk()->assertDownload();
        $this->assertInstanceOf(BinaryFileResponse::class, $xlsx->baseResponse);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($xlsx->baseResponse->getFile()->getPathname()));
        $worksheet = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
        $sharedStrings = (string) $archive->getFromName('xl/sharedStrings.xml');
        $archive->close();
        $xlsxContent = $worksheet.$sharedStrings;
        $this->assertStringContainsString('Suppressed (&lt;5)', $xlsxContent);
        $this->assertStringNotContainsString('97.25', $xlsxContent);

        $pdf = $this->actingAs($admin)->get(route('learning.analytics.export', ['pdf']));
        $pdf->assertOk()->assertDownload();
        $pdfContent = $pdf->getContent();
        $this->assertIsString($pdfContent);
        $this->assertStringNotContainsString('97.25', $pdfContent);
    }

    public function test_learning_analytics_interface_and_tabular_exports_follow_the_active_locale(): void
    {
        config()->set('analytics.minimum_aggregate_cell_size', 5);
        $county = County::factory()->create();
        $course = LearningCourse::factory()->create(['status' => 'published']);
        $admin = User::factory()->devolutionAdmin()->create();
        $this->enrollment($course, $county, 'completed', 100, 88);

        $this->actingAs($admin)
            ->withSession(['locale' => 'sw'])
            ->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'sw')
                ->where('localization.learningAnalytics.title', 'Uchanganuzi wa mafunzo')
                ->where('localization.learningAnalytics.suppressed', 'Imefichwa (<:count)'));

        $csv = $this->actingAs($admin)
            ->withSession(['locale' => 'sw'])
            ->get(route('learning.analytics.export', ['csv']));

        $csv->assertOk()->assertDownload();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Msimbo wa kaunti', $content);
        $this->assertStringContainsString('Imefichwa (<5)', $content);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'learning.analytics.exported',
            'description' => 'Uchanganuzi wa mafunzo umehamishwa kama CSV.',
        ]);
    }

    public function test_published_knowledge_recommendations_flow_back_to_authorized_courses(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $learner = User::factory()->countyOfficial($home)->create();
        $course = LearningCourse::factory()->create(['status' => 'published', 'county_id' => null]);
        $nationalItem = KnowledgeItem::factory()->create(['reference' => 'KM-GUIDE-001', 'title' => 'County planning field guide', 'status' => 'published', 'county_id' => null]);
        $hiddenItem = KnowledgeItem::factory()->create(['reference' => 'KM-HIDDEN-002', 'status' => 'published', 'county_id' => $other->id]);
        $course->knowledgeItems()->attach([$nationalItem->id, $hiddenItem->id]);

        $this->actingAs($learner)->get(route('learning.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('courses.data.0.knowledgeRecommendations.0.reference', 'KM-GUIDE-001')
                ->where('courses.data.0.knowledgeRecommendations.0.title', 'County planning field guide')
                ->has('courses.data.0.knowledgeRecommendations', 1)
            );
    }

    public function test_question_bank_item_analysis_is_scoped_privacy_protected_and_lineage_bound(): void
    {
        $county = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $course = LearningCourse::factory()->create(['status' => 'published']);
        $module = LearningModule::factory()->create(['learning_course_id' => $course->id]);
        $lesson = LearningLesson::factory()->create(['learning_module_id' => $module->id, 'content_type' => 'quiz']);
        $frequentlyMissed = LearningQuizQuestion::factory()->create(['learning_lesson_id' => $lesson->id, 'question' => 'Which control prevents self-approval?', 'sequence' => 1]);
        $sparseQuestion = LearningQuizQuestion::factory()->create(['learning_lesson_id' => $lesson->id, 'question' => 'Which checksum protects the bank?', 'sequence' => 2]);
        $bank = LearningQuestionBank::factory()->create(['learning_course_id' => $course->id, 'created_by' => $course->owner_id, 'version' => 3, 'checksum' => str_repeat('a', 64), 'status' => 'draft']);
        LearningQuestionBankItem::factory()->create(['learning_question_bank_id' => $bank->id, 'learning_quiz_question_id' => $frequentlyMissed->id, 'variant_group' => 'separation-of-duties', 'difficulty' => 'advanced', 'tags' => ['governance'], 'sequence' => 1]);
        LearningQuestionBankItem::factory()->create(['learning_question_bank_id' => $bank->id, 'learning_quiz_question_id' => $sparseQuestion->id, 'variant_group' => 'integrity', 'difficulty' => 'standard', 'tags' => ['checksum'], 'sequence' => 2]);
        $bank->update(['status' => 'published']);
        $admin = User::factory()->countyAdmin($county)->create();
        $scores = [90.0, 40.0, 50.0];
        foreach ($scores as $index => $score) {
            $enrollment = $this->enrollment($course, $county, 'completed', 100, $score);
            $questions = [[
                'question_id' => $frequentlyMissed->id,
                'answer' => $index === 0 ? 'a' : 'b',
                'correct' => $index === 0,
                'points' => 1.0,
            ]];
            if ($index === 0) {
                $questions[] = ['question_id' => $sparseQuestion->id, 'answer' => 'a', 'correct' => true, 'points' => 1.0];
            }
            LearningAssessmentAttempt::factory()->create([
                'learning_enrollment_id' => $enrollment->id,
                'attempt_number' => 1,
                'score' => $score,
                'result_snapshot' => ['question_bank_id' => $bank->id, 'question_bank_checksum' => $bank->checksum, 'questions' => $questions],
            ]);
        }
        $outsideEnrollment = $this->enrollment($course, $outsideCounty, 'completed', 100, 100);
        LearningAssessmentAttempt::factory()->create([
            'learning_enrollment_id' => $outsideEnrollment->id,
            'result_snapshot' => ['question_bank_id' => $bank->id, 'question_bank_checksum' => $bank->checksum, 'questions' => [['question_id' => $frequentlyMissed->id, 'correct' => true, 'points' => 1.0]]],
        ]);
        $newerBank = LearningQuestionBank::factory()->create(['learning_course_id' => $course->id, 'created_by' => $course->owner_id, 'version' => 4, 'checksum' => str_repeat('b', 64), 'status' => 'draft']);
        LearningQuestionBankItem::factory()->create(['learning_question_bank_id' => $newerBank->id, 'learning_quiz_question_id' => $frequentlyMissed->id, 'variant_group' => 'updated-control', 'difficulty' => 'advanced', 'tags' => ['updated'], 'sequence' => 1]);
        $emptyEnrollment = $this->enrollment($course, $county, 'completed', 100, 0);
        LearningAssessmentAttempt::factory()->create([
            'learning_enrollment_id' => $emptyEnrollment->id,
            'score' => 0,
            'result_snapshot' => ['question_bank_id' => $newerBank->id, 'question_bank_checksum' => $newerBank->checksum, 'questions' => []],
        ]);

        $this->actingAs($admin)->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.questionBank.hasData', true)
                ->where('report.questionBank.attempts', 3)
                ->where('report.questionBank.lineages', 1)
                ->where('report.questionBank.rows.0.question', 'Which control prevents self-approval?')
                ->where('report.questionBank.rows.0.responseCount', 3)
                ->where('report.questionBank.rows.0.correctRate', 33.33)
                ->where('report.questionBank.rows.0.discrimination', 45)
                ->where('report.questionBank.rows.0.variantGroup', 'separation-of-duties')
                ->where('report.questionBank.rows.0.bankVersion', 3)
                ->where('report.questionBank.rows.0.bankChecksum', str_repeat('a', 64))
                ->where('report.questionBank.rows.1.question', 'Which checksum protects the bank?')
                ->where('report.questionBank.rows.1.suppressed', true)
                ->where('report.questionBank.rows.1.responseCount', null)
                ->where('report.questionBank.pagination.pageName', 'question_page')
                ->has('report.questionBank.rows', 2));

        $newerEnrollment = $this->enrollment($course, $county, 'completed', 100, 75);
        LearningAssessmentAttempt::factory()->create([
            'learning_enrollment_id' => $newerEnrollment->id,
            'score' => 75,
            'result_snapshot' => ['question_bank_id' => $newerBank->id, 'question_bank_checksum' => $newerBank->checksum, 'questions' => [['question_id' => $frequentlyMissed->id, 'correct' => false, 'points' => 1.0]]],
        ]);

        $this->actingAs($admin)->get(route('learning.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.questionBank.attempts', 4)
                ->where('report.questionBank.lineages', 2)
                ->where('report.questionBank.rows.0.lineageCount', 2)
                ->where('report.questionBank.rows.0.bankVersion', null)
                ->where('report.questionBank.rows.0.bankChecksum', null));
    }

    private function enrollment(LearningCourse $course, County $county, string $status, float $progress, ?float $score, string $date = '2026-08-01'): LearningEnrollment
    {
        return LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'county_id' => $county->id, 'status' => $status, 'progress_percentage' => $progress, 'best_score' => $score, 'enrolled_at' => $date]);
    }
}
