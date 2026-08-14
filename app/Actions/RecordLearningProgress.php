<?php

namespace App\Actions;

use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecordLearningProgress
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string,mixed> $attributes */
    public function handle(LearningEnrollment $enrollment, LearningLesson $lesson, User $actor, array $attributes): LearningEnrollment
    {
        abort_unless($enrollment->user_id === $actor->id, 403);
        abort_unless($lesson->module()->where('learning_course_id', $enrollment->learning_course_id)->exists(), 422);
        abort_if($lesson->content_type === 'quiz', 422, __('learning.progress.errors.quiz_engine_required'));

        return DB::transaction(function () use ($enrollment, $lesson, $actor, $attributes): LearningEnrollment {
            $lockedEnrollment = LearningEnrollment::query()->whereKey($enrollment->id)->lockForUpdate()->sole();
            $lockedEnrollment->progress()->updateOrCreate(['learning_lesson_id' => $lesson->id], ['status' => 'completed', 'progress_percentage' => 100, 'time_spent_seconds' => $attributes['time_spent_seconds'], 'started_at' => $lockedEnrollment->started_at ?? now(), 'completed_at' => now(), 'last_position_at' => now(), 'state' => $attributes['state'] ?? null]);
            $this->recalculate($lockedEnrollment);
            $this->auditLogger->record($actor, $lockedEnrollment, 'learning.lesson.completed', __('learning.progress.audit.lesson_completed', ['lesson' => $lesson->title]), $actor->county_id, ['lesson_id' => $lesson->id]);

            return $lockedEnrollment->refresh();
        });
    }

    public function recalculate(LearningEnrollment $enrollment): void
    {
        $required = $enrollment->course->modules()->with('lessons')->get()->flatMap->lessons->where('is_required', true);
        $completed = $enrollment->progress()->where('status', 'completed')->whereIn('learning_lesson_id', $required->pluck('id'))->count();
        $percentage = $required->count() ? round(($completed / $required->count()) * 100, 2) : 0;
        $enrollment->update(['status' => $percentage > 0 ? 'in_progress' : $enrollment->status, 'progress_percentage' => $percentage, 'started_at' => $enrollment->started_at ?? now(), 'last_activity_at' => now()]);
    }
}
