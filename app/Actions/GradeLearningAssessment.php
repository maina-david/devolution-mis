<?php

namespace App\Actions;

use App\Models\LearningCertificate;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeLearningAssessment
{
    public function __construct(private RecordLearningProgress $progress, private AuditLogger $auditLogger) {}

    /** @param array<string,string> $answers */
    public function handle(LearningEnrollment $enrollment, User $actor, array $answers): LearningEnrollment
    {
        abort_unless($enrollment->user_id === $actor->id, 403);
        $course = $enrollment->course()->with('modules.lessons.questions')->firstOrFail();
        $questions = $course->modules->flatMap->lessons->flatMap->questions;
        $attemptNumber = $enrollment->attempts()->count() + 1;
        if ($attemptNumber > $course->maximum_attempts) {
            throw ValidationException::withMessages(['answers' => 'Maximum assessment attempts reached.']);
        }$nonQuizRequired = $course->modules->flatMap->lessons->where('is_required', true)->where('content_type', '!=', 'quiz');
        $completed = $enrollment->progress()->where('status', 'completed')->whereIn('learning_lesson_id', $nonQuizRequired->pluck('id'))->count();
        if ($completed < $nonQuizRequired->count()) {
            throw ValidationException::withMessages(['answers' => 'Complete all required learning content before the assessment.']);
        }

        return DB::transaction(function () use ($enrollment, $actor, $answers, $questions, $attemptNumber, $course): LearningEnrollment {
            $earned = 0.0;
            $total = (float) $questions->sum('points');
            $results = [];
            foreach ($questions as $question) {
                $answer = $answers[$question->id] ?? null;
                $correct = $answer === $question->correct_option;
                if ($correct) {
                    $earned += (float) $question->points;
                }$results[] = ['question_id' => $question->id, 'answer' => $answer, 'correct' => $correct, 'points' => (float) $question->points];
            }$score = $total > 0 ? round(($earned / $total) * 100, 2) : 0;
            $passed = $score >= (float) $course->passing_score;
            $enrollment->attempts()->create(['attempt_number' => $attemptNumber, 'answers' => $answers, 'result_snapshot' => $results, 'score' => $score, 'passed' => $passed, 'submitted_at' => now()]);
            if ($passed) {
                $quizLessons = $course->modules->flatMap->lessons->where('content_type', 'quiz');
                foreach ($quizLessons as $lesson) {
                    $enrollment->progress()->updateOrCreate(['learning_lesson_id' => $lesson->id], ['status' => 'completed', 'progress_percentage' => 100, 'time_spent_seconds' => 0, 'started_at' => now(), 'completed_at' => now(), 'last_position_at' => now()]);
                }$this->progress->recalculate($enrollment);
                $enrollment->update(['status' => 'completed', 'progress_percentage' => 100, 'best_score' => max($score, (float) ($enrollment->best_score ?? 0)), 'completed_at' => now(), 'last_activity_at' => now()]);
                $this->certificate($enrollment, $actor, $score);
            } else {
                $enrollment->update(['best_score' => max($score, (float) ($enrollment->best_score ?? 0)), 'last_activity_at' => now()]);
            }$this->auditLogger->record($actor, $enrollment, 'learning.assessment.submitted', "Assessment attempt {$attemptNumber} scored {$score}%.", $actor->county_id, ['passed' => $passed]);

            return $enrollment->refresh();
        });
    }

    private function certificate(LearningEnrollment $enrollment, User $actor, float $score): LearningCertificate
    {
        $number = 'IDMIS-LRN-'.now()->format('Y').'-'.strtoupper(substr(str_replace('-', '', $enrollment->id), 0, 10));
        $verification = hash('sha256', $enrollment->id.'|'.$score.'|'.now()->toIso8601String());
        $payload = ['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->learning_course_id, 'learner_id' => $enrollment->user_id, 'score' => $score, 'issued_at' => now()->toIso8601String()];

        return $enrollment->certificate()->firstOrCreate([], ['certificate_number' => $number, 'verification_code' => substr($verification, 0, 24), 'content_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'final_score' => $score, 'issued_at' => now(), 'issued_by' => $actor->id]);
    }
}
