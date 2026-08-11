<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $learning_enrollment_id
 * @property string $learning_lesson_id
 * @property string $status
 * @property numeric-string $progress_percentage
 * @property int $time_spent_seconds
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $last_position_at
 * @property array<string, mixed>|null $state
 */
#[Fillable(['learning_enrollment_id', 'learning_lesson_id', 'status', 'progress_percentage', 'time_spent_seconds', 'started_at', 'completed_at', 'last_position_at', 'state'])]
class LearningProgress extends Model
{
    /** @use HasFactory<LearningProgressFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['progress_percentage' => 'decimal:2', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'last_position_at' => 'immutable_datetime', 'state' => 'array'];
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }
}
