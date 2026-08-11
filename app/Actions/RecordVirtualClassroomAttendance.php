<?php

namespace App\Actions;

use App\Models\LearningEnrollment;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Models\VirtualClassroomAttendance;
use App\Services\AuditLogger;
use App\Services\VirtualClassroomAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordVirtualClassroomAttendance
{
    public function __construct(private VirtualClassroomAccess $access, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(VirtualClassroom $classroom, User $actor, array $attributes): VirtualClassroomAttendance
    {
        return DB::transaction(function () use ($classroom, $actor, $attributes): VirtualClassroomAttendance {
            $lockedClassroom = VirtualClassroom::query()->with('course.county')->lockForUpdate()->findOrFail($classroom->id);
            abort_unless($this->access->canManageAttendance($actor, $lockedClassroom), 403);
            abort_if(now()->isBefore($lockedClassroom->starts_at), 409, 'Attendance cannot be recorded before the classroom starts.');

            $enrollmentId = (string) $attributes['learning_enrollment_id'];
            $enrollment = LearningEnrollment::query()->with('user')->lockForUpdate()->findOrFail($enrollmentId);
            abort_unless($enrollment->learning_course_id === $lockedClassroom->learning_course_id && $enrollment->user_id === $enrollment->user->id, 409, 'The learner is not enrolled in this classroom course.');

            $normalized = $this->normalizedAttendance($lockedClassroom, $enrollment, $attributes);
            $checksum = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
            $providerEventId = $normalized['provider_event_id'];

            if (is_string($providerEventId)) {
                $existingEvent = VirtualClassroomAttendance::query()->where('virtual_classroom_id', $lockedClassroom->id)->where('provider_event_id', $providerEventId)->first();
                if ($existingEvent !== null) {
                    abort_unless(hash_equals($existingEvent->payload_checksum, $checksum), 409, 'The provider event identifier was already used with different attendance data.');

                    return $existingEvent;
                }
            }

            $attendance = VirtualClassroomAttendance::query()->where('virtual_classroom_id', $lockedClassroom->id)->where('learning_enrollment_id', $enrollment->id)->first();
            if ($attendance !== null) {
                abort_if(trim((string) ($attributes['notes'] ?? '')) === '', 422, 'An attendance amendment requires an explanatory note.');
            }

            if ($normalized['attendance_status'] !== 'absent' && $lockedClassroom->capacity !== null) {
                $occupied = VirtualClassroomAttendance::query()->where('virtual_classroom_id', $lockedClassroom->id)->where('learning_enrollment_id', '!=', $enrollment->id)->whereIn('attendance_status', ['present', 'partial'])->count();
                abort_if($occupied >= $lockedClassroom->capacity, 409, 'The classroom attendance capacity has been reached.');
            }

            $beforeChecksum = $attendance?->payload_checksum;
            $values = [...Arr::except($normalized, ['learning_enrollment_id']), 'learning_enrollment_id' => $enrollment->id, 'payload_checksum' => $checksum, 'recorded_by' => $actor->id, 'recorded_at' => now()];
            if ($attendance === null) {
                $attendance = VirtualClassroomAttendance::create($values);
            } else {
                $attendance->update($values);
            }

            $this->auditLogger->record($actor, $attendance, $beforeChecksum === null ? 'learning.classroom_attendance_recorded' : 'learning.classroom_attendance_amended', "{$normalized['attendance_status']} attendance recorded for {$enrollment->user->name} in {$lockedClassroom->title}.", $enrollment->county_id, ['classroom_id' => $lockedClassroom->id, 'enrollment_id' => $enrollment->id, 'source' => $normalized['source'], 'attended_minutes' => $normalized['attended_minutes'], 'before_checksum' => $beforeChecksum, 'payload_checksum' => $checksum]);

            return $attendance->refresh();
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array{virtual_classroom_id: string, learning_enrollment_id: string, user_id: string, attendance_status: string, joined_at: string|null, left_at: string|null, attended_minutes: int, source: string, provider_event_id: string|null, notes: string|null}
     */
    private function normalizedAttendance(VirtualClassroom $classroom, LearningEnrollment $enrollment, array $attributes): array
    {
        $status = (string) $attributes['attendance_status'];
        $source = (string) $attributes['source'];
        $providerEventId = isset($attributes['provider_event_id']) ? trim((string) $attributes['provider_event_id']) : null;
        $notes = isset($attributes['notes']) ? trim((string) $attributes['notes']) : null;
        $joinedAt = null;
        $leftAt = null;
        $attendedMinutes = 0;

        if ($status !== 'absent') {
            $joinedAt = CarbonImmutable::parse((string) $attributes['joined_at']);
            $leftAt = CarbonImmutable::parse((string) $attributes['left_at']);
            if ($joinedAt->isBefore($classroom->starts_at) || $leftAt->isAfter($classroom->ends_at) || ! $leftAt->isAfter($joinedAt)) {
                throw ValidationException::withMessages(['joined_at' => 'Attendance times must fall within the classroom start and end times.']);
            }

            $attendedMinutes = (int) $joinedAt->diffInMinutes($leftAt);
            $sessionMinutes = max(1, (int) $classroom->starts_at->diffInMinutes($classroom->ends_at));
            $expectedStatus = $attendedMinutes >= (int) ceil($sessionMinutes * 0.75) ? 'present' : 'partial';
            if ($status !== $expectedStatus) {
                throw ValidationException::withMessages(['attendance_status' => "The recorded duration is classified as {$expectedStatus} attendance."]);
            }
        }

        return ['virtual_classroom_id' => $classroom->id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $enrollment->user_id, 'attendance_status' => $status, 'joined_at' => $joinedAt?->toIso8601String(), 'left_at' => $leftAt?->toIso8601String(), 'attended_minutes' => $attendedMinutes, 'source' => $source, 'provider_event_id' => $providerEventId, 'notes' => $notes];
    }
}
