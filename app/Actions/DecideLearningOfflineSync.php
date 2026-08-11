<?php

namespace App\Actions;

use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningOfflineSync;
use App\Models\LearningProgress;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LearningOfflineSyncReconciler;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DecideLearningOfflineSync
{
    public function __construct(private LearningOfflineSyncReconciler $reconciler, private RecordLearningProgress $progressAction, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array{decision: string, rationale: string} $attributes */
    public function handle(LearningOfflineSync $sync, User $actor, array $attributes): LearningOfflineSync
    {
        return DB::transaction(function () use ($sync, $actor, $attributes): LearningOfflineSync {
            $locked = LearningOfflineSync::query()->whereKey($sync->id)->lockForUpdate()->sole();
            abort_unless($locked->status === 'pending', 409, 'This offline synchronization has already received a final decision.');
            abort_if($locked->submitted_by === $actor->id, 403, 'Offline activity requires an independent reviewer.');
            abort_unless($locked->county_id === null ? $actor->programmeRole()->hasNationalScope() : $actor->canAccessCounty($locked->county), 403);
            abort_unless(hash_equals($locked->payload_checksum, $this->canonicalJson->checksum($locked->payload)), 409, 'Offline synchronization payload integrity verification failed.');

            $enrollment = LearningEnrollment::query()->whereKey($locked->learning_enrollment_id)->lockForUpdate()->sole();
            $status = $attributes['decision'] === 'reject' ? 'rejected' : 'approved';
            $applied = [];
            if ($status === 'approved' && ! hash_equals($locked->base_progress_checksum, $this->reconciler->progressChecksum($enrollment))) {
                $status = 'conflict';
            }

            if ($status === 'approved') {
                /** @var list<array<string, mixed>> $events */
                $events = $locked->payload['events'];
                $lessons = LearningLesson::query()->whereIn('id', collect($events)->pluck('lesson_id'))->whereHas('module', fn ($query) => $query->where('learning_course_id', $enrollment->learning_course_id))->get()->keyBy('id');
                abort_unless($lessons->count() === count($events), 409, 'The synchronized course structure is no longer available.');
                $enrollment->progress()->whereIn('learning_lesson_id', $lessons->keys())->lockForUpdate()->get();

                foreach ($events as $event) {
                    $lesson = $lessons->get((string) $event['lesson_id']);
                    abort_unless($lesson instanceof LearningLesson && $lesson->content_type !== 'quiz', 409, 'Offline quiz activity cannot update official progress.');
                    $occurredAt = CarbonImmutable::parse((string) $event['occurred_at']);
                    $current = LearningProgress::query()->withTrashed()->where('learning_enrollment_id', $enrollment->id)->where('learning_lesson_id', $lesson->id)->first();
                    abort_if($current !== null && ((float) $current->progress_percentage > (float) $event['progress_percentage'] || ($current->last_position_at !== null && $current->last_position_at->greaterThan($occurredAt))), 409, 'Offline activity would regress a newer official learning record.');
                    if ($current?->trashed()) {
                        $current->restore();
                    }
                    $state = is_array($event['state'] ?? null) ? $event['state'] : [];
                    $currentTimeSpent = $current instanceof LearningProgress ? $current->time_spent_seconds : 0;
                    $currentStartedAt = $current instanceof LearningProgress ? $current->started_at : null;
                    $values = [
                        'status' => $event['status'],
                        'progress_percentage' => $event['progress_percentage'],
                        'time_spent_seconds' => max($currentTimeSpent, (int) $event['time_spent_seconds']),
                        'started_at' => $currentStartedAt ?? $occurredAt,
                        'completed_at' => $event['status'] === 'completed' ? $occurredAt : null,
                        'last_position_at' => $occurredAt,
                        'state' => [...$state, '_offline_sync' => ['sync_id' => $locked->id, 'client_event_id' => $event['client_event_id'], 'device_id' => $locked->device_id]],
                    ];
                    if ($current instanceof LearningProgress) {
                        $current->update($values);
                    } else {
                        $enrollment->progress()->create(['learning_lesson_id' => $lesson->id, ...$values]);
                    }
                    $applied[] = ['lesson_id' => $lesson->id, 'status' => $event['status'], 'progress_percentage' => $event['progress_percentage'], 'occurred_at' => $occurredAt->toIso8601String()];
                }
                $this->progressAction->recalculate($enrollment);
            }

            $reviewedAt = now();
            $decision = [
                'sync_id' => $locked->id,
                'payload_checksum' => $locked->payload_checksum,
                'base_progress_checksum' => $locked->base_progress_checksum,
                'reviewer_id' => $actor->id,
                'status' => $status,
                'rationale' => $attributes['rationale'],
                'applied' => $applied,
                'reviewed_at' => $reviewedAt->toIso8601String(),
            ];
            $locked->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_by_name' => $actor->name,
                'decision_reason' => $attributes['rationale'],
                'decision_checksum' => $this->canonicalJson->checksum($decision),
                'reviewed_at' => $reviewedAt,
                'applied_at' => $status === 'approved' ? $reviewedAt : null,
            ]);
            $this->auditLogger->record($actor, $locked, 'learning.offline-sync.'.$status, "Offline learning synchronization {$status}.", $locked->county_id, ['payload_checksum' => $locked->payload_checksum, 'decision_checksum' => $locked->decision_checksum, 'event_count' => $locked->event_count]);

            return $locked->refresh();
        });
    }
}
