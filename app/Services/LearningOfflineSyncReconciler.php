<?php

namespace App\Services;

use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningOfflinePackage;
use App\Models\LearningProgress;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class LearningOfflineSyncReconciler
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateAndNormalize(LearningEnrollment $enrollment, LearningOfflinePackage $package, array $payload): array
    {
        abort_unless($package->status === 'ready', 409, __('learning.offline.errors.package_not_ready'));
        abort_unless($package->learning_course_id === $enrollment->learning_course_id, 422, __('learning.offline.errors.package_course_mismatch'));
        abort_unless(($payload['package_id'] ?? null) === $package->id, 422, __('learning.offline.errors.package_id_mismatch'));
        abort_unless(is_string($package->manifest_checksum) && hash_equals($package->manifest_checksum, (string) ($payload['package_manifest_checksum'] ?? '')), 409, __('learning.offline.errors.manifest_checksum_mismatch'));
        abort_unless($package->generated_at !== null, 409, __('learning.offline.errors.generation_time_missing'));

        /** @var list<array<string, mixed>> $events */
        $events = $payload['events'];
        $lessonIds = collect($events)->pluck('lesson_id')->all();
        /** @var Collection<int, LearningLesson> $lessons */
        $lessons = LearningLesson::query()->whereIn('id', $lessonIds)->whereHas('module', fn ($query) => $query->where('learning_course_id', $enrollment->learning_course_id))->get()->keyBy('id');
        abort_unless($lessons->count() === count($lessonIds), 422, __('learning.offline.errors.lessons_outside_course'));

        $exportedAt = CarbonImmutable::parse((string) $payload['exported_at']);
        $existing = $enrollment->progress()->whereIn('learning_lesson_id', $lessonIds)->get()->keyBy('learning_lesson_id');
        foreach ($events as &$event) {
            $lesson = $lessons->get((string) $event['lesson_id']);
            abort_unless($lesson instanceof LearningLesson && $lesson->content_type !== 'quiz', 422, __('learning.offline.errors.quiz_progress_engine'));
            $percentage = (float) $event['progress_percentage'];
            abort_unless(($event['status'] === 'completed' && $percentage === 100.0) || ($event['status'] === 'in_progress' && $percentage < 100.0), 422, __('learning.offline.errors.status_progress_mismatch'));
            $occurredAt = CarbonImmutable::parse((string) $event['occurred_at']);
            abort_unless($occurredAt->greaterThanOrEqualTo($package->generated_at) && $occurredAt->lessThanOrEqualTo($exportedAt), 422, __('learning.offline.errors.activity_window_invalid'));
            $current = $existing->get((string) $event['lesson_id']);
            abort_if($current !== null && ((float) $current->progress_percentage > $percentage || ($current->last_position_at !== null && $current->last_position_at->greaterThan($occurredAt))), 409, __('learning.offline.errors.progress_regression'));
            $event['progress_percentage'] = $percentage;
            $event['time_spent_seconds'] = (int) $event['time_spent_seconds'];
            $event['occurred_at'] = $occurredAt->toIso8601String();
        }
        unset($event);
        usort($events, fn (array $left, array $right): int => [$left['lesson_id'], $left['occurred_at'], $left['client_event_id']] <=> [$right['lesson_id'], $right['occurred_at'], $right['client_event_id']]);

        return [...$payload, 'events' => $events, 'exported_at' => $exportedAt->toIso8601String()];
    }

    public function progressChecksum(LearningEnrollment $enrollment): string
    {
        $lessonIds = LearningLesson::query()->whereHas('module', fn ($query) => $query->where('learning_course_id', $enrollment->learning_course_id))->where('content_type', '!=', 'quiz')->orderBy('id')->pluck('id');
        $progress = $enrollment->progress()->whereIn('learning_lesson_id', $lessonIds)->orderBy('learning_lesson_id')->get();

        return $this->canonicalJson->checksum([
            'enrollment_id' => $enrollment->id,
            'course_id' => $enrollment->learning_course_id,
            'progress' => $progress->map(fn (LearningProgress $item): array => [
                'lesson_id' => $item->learning_lesson_id,
                'status' => $item->status,
                'progress_percentage' => (string) $item->progress_percentage,
                'time_spent_seconds' => $item->time_spent_seconds,
                'last_position_at' => $item->last_position_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
