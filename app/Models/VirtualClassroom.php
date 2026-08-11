<?php

namespace App\Models;

use Database\Factories\VirtualClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['learning_course_id', 'facilitator_id', 'title', 'description', 'starts_at', 'ends_at', 'platform', 'join_url', 'recording_url', 'capacity', 'status', 'created_by'])]
class VirtualClassroom extends Model
{
    /** @use HasFactory<VirtualClassroomFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    /** @return HasMany<VirtualClassroomAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(VirtualClassroomAttendance::class);
    }
}
