<?php

namespace App\Actions;

use App\Models\LearningCourse;
use App\Models\LearningLesson;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionLearningCourse
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string,mixed> $attributes */
    public function handle(LearningCourse $course, User $actor, array $attributes): LearningCourse
    {
        if (in_array($attributes['transition'], ['submit_review', 'publish'], true)) {
            $assetLessons = $course->modules()->with(['lessons.documentLinks.document'])->get()->flatMap->lessons->filter(fn (LearningLesson $lesson): bool => $lesson->is_required && in_array($lesson->content_type, ['video', 'audio', 'toolkit', 'manual'], true));
            $missing = $assetLessons->filter(function (LearningLesson $lesson): bool {
                $hasCleanAsset = $lesson->documentLinks->contains(fn ($link): bool => $link->document->record_status === 'active' && $link->document->scan_status === 'clean');
                $metadata = $lesson->assetMetadata();
                $hasAlternative = is_string($metadata['accessible_alternative'] ?? null) && trim($metadata['accessible_alternative']) !== '';
                $hasTranscript = ! in_array($lesson->content_type, ['video', 'audio'], true) || ($metadata['transcript_available'] ?? false) === true;

                return ! $hasCleanAsset || ! $hasAlternative || ! $hasTranscript;
            });
            abort_if($missing->isNotEmpty(), 409, __('learning.course_transition.errors.accessible_assets_required'));
        }

        return DB::transaction(function () use ($course, $actor, $attributes): LearningCourse {
            $name = (string) $attributes['transition'];
            $instance = $this->transitionWorkflow->handle($course->workflowInstance()->firstOrFail(), $name, $actor, [], $attributes['rationale']);
            $course->update(['status' => $instance->current_state, 'published_at' => $name === 'publish' ? now() : $course->published_at, 'retired_at' => $name === 'retire' ? now() : $course->retired_at]);
            $this->auditLogger->record($actor, $course, 'learning.course.transitioned', __('learning.course_transition.audit.transitioned', ['course' => $course->code, 'status' => $instance->current_state]), null, ['transition' => $name]);

            return $course->refresh();
        });
    }
}
