<?php

namespace App\Models;

use Database\Factories\VirtualClassroomAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property Carbon $recorded_at
 * @property int $attended_minutes
 * @property string $attendance_status
 * @property string $source
 * @property string $payload_checksum
 */
#[Fillable(['virtual_classroom_id', 'learning_enrollment_id', 'user_id', 'attendance_status', 'joined_at', 'left_at', 'attended_minutes', 'source', 'provider_event_id', 'payload_checksum', 'notes', 'recorded_by', 'recorded_at'])]
class VirtualClassroomAttendance extends Model
{
    /** @use HasFactory<VirtualClassroomAttendanceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['attendance_status' => 'absent', 'attended_minutes' => 0, 'source' => 'manual'];

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'attended_minutes' => 'integer', 'recorded_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<VirtualClassroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(VirtualClassroom::class, 'virtual_classroom_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
