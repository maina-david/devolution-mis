<?php

namespace Tests\Feature;

use App\Actions\GradeLearningAssessment;
use App\Models\AuditEvent;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningModule;
use App\Models\LearningQuestionBank;
use App\Models\LearningQuestionBankItem;
use App\Models\LearningQuizQuestion;
use App\Models\User;
use App\Services\LearningQuestionSelector;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LearningQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_selection_is_deterministic_bounded_and_one_per_group(): void
    {
        [$bank, $enrollment] = $this->bankFixture();
        $selector = app(LearningQuestionSelector::class);

        $first = $selector->select($bank, $enrollment->id, 1);
        $replay = $selector->select($bank, $enrollment->id, 1);

        $this->assertCount(2, $first);
        $this->assertSame($first->modelKeys(), $replay->modelKeys());
        $groups = $bank->items->whereIn('learning_quiz_question_id', $first->modelKeys())->pluck('variant_group');
        $this->assertCount(2, $groups->unique());
    }

    public function test_grading_rejects_questions_outside_the_attempt_variant_and_snapshots_bank_lineage(): void
    {
        [$bank, $enrollment] = $this->bankFixture();
        $selected = app(LearningQuestionSelector::class)->select($bank, $enrollment->id, 1);
        $hidden = $bank->items->first(fn (LearningQuestionBankItem $item): bool => ! $selected->contains('id', $item->learning_quiz_question_id))->question;
        App::setLocale('fr');

        try {
            app(GradeLearningAssessment::class)->handle($enrollment, $enrollment->user, [$hidden->id => $hidden->correct_option]);
            $this->fail('An answer outside the selected bank variant was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(trans('learning.assessment_engine.errors.outside_variant', locale: 'fr'), $exception->errors()['answers'][0]);
        }

        $answers = $selected->mapWithKeys(fn (LearningQuizQuestion $question): array => [$question->id => $question->correct_option])->all();
        App::setLocale('sw');
        app(GradeLearningAssessment::class)->handle($enrollment, $enrollment->user, $answers);
        $snapshot = $enrollment->attempts()->sole()->result_snapshot;
        $this->assertSame($bank->id, $snapshot['question_bank_id']);
        $this->assertSame($bank->checksum, $snapshot['question_bank_checksum']);
        $this->assertCount(2, $snapshot['questions']);
        $event = AuditEvent::query()->where('subject_id', $enrollment->id)->where('action', 'learning.assessment.submitted')->sole();
        $this->assertSame(trans('learning.assessment_engine.audit.submitted', ['attempt' => 1, 'score' => 100], 'sw'), $event->description);
    }

    public function test_published_question_bank_and_items_are_database_immutable(): void
    {
        [$bank] = $this->bankFixture();
        $this->expectException(QueryException::class);
        $bank->update(['title' => 'Attempted mutation']);
    }

    /** @return array{LearningQuestionBank, LearningEnrollment} */
    private function bankFixture(): array
    {
        $learner = User::factory()->create();
        $course = LearningCourse::factory()->create(['passing_score' => 50]);
        $module = LearningModule::factory()->create(['learning_course_id' => $course->id]);
        $lesson = LearningLesson::factory()->create(['learning_module_id' => $module->id, 'content_type' => 'quiz']);
        $questions = collect([
            ['group' => 'objective-a', 'difficulty' => 'foundation'], ['group' => 'objective-a', 'difficulty' => 'advanced'],
            ['group' => 'objective-b', 'difficulty' => 'standard'], ['group' => 'objective-c', 'difficulty' => 'standard'],
        ])->map(fn (array $variant, int $index): array => ['question' => LearningQuizQuestion::factory()->create(['learning_lesson_id' => $lesson->id, 'sequence' => $index + 1]), ...$variant]);
        $bank = LearningQuestionBank::factory()->create(['learning_course_id' => $course->id, 'created_by' => $course->owner_id, 'selection_count' => 2, 'status' => 'draft']);
        foreach ($questions as $index => $variant) {
            LearningQuestionBankItem::factory()->create(['learning_question_bank_id' => $bank->id, 'learning_quiz_question_id' => $variant['question']->id, 'variant_group' => $variant['group'], 'difficulty' => $variant['difficulty'], 'sequence' => $index + 1]);
        }
        $bank->update(['status' => 'published']);
        $enrollment = LearningEnrollment::factory()->create(['learning_course_id' => $course->id, 'user_id' => $learner->id, 'enrolled_by' => $learner->id]);

        return [$bank->load('items.question'), $enrollment->load('user')];
    }
}
