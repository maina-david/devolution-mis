<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningCohortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $learning_course_id
 * @property string $instructor_id
 * @property string|null $county_id
 * @property string $created_by
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property CarbonImmutable $enrollment_opens_on
 * @property CarbonImmutable $enrollment_closes_on
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $status
 * @property int $memberships_count
 */
#[Fillable(['learning_course_id', 'instructor_id', 'county_id', 'created_by', 'code', 'name', 'description', 'capacity', 'enrollment_opens_on', 'enrollment_closes_on', 'starts_at', 'ends_at', 'status', 'transitioned_by', 'transitioned_at'])]
class LearningCohort extends Model
{
    /** @use HasFactory<LearningCohortFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'enrollment_opens_on' => 'date', 'enrollment_closes_on' => 'date', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'transitioned_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasMany<LearningCohortMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(LearningCohortMembership::class);
    }
}
