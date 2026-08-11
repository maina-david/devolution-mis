<?php

namespace App\Models;

use Database\Factories\LearningEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['learning_course_id', 'user_id', 'county_id', 'organization_id', 'status', 'progress_percentage', 'best_score', 'enrolled_at', 'started_at', 'last_activity_at', 'completed_at', 'enrolled_by'])]
class LearningEnrollment extends Model
{
    /** @use HasFactory<LearningEnrollmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['progress_percentage' => 'decimal:2', 'best_score' => 'decimal:2', 'enrolled_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'last_activity_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasMany<LearningProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(LearningProgress::class);
    }

    /** @return HasMany<LearningAssessmentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(LearningAssessmentAttempt::class);
    }

    /** @return HasOne<LearningCertificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(LearningCertificate::class);
    }

    /** @return HasMany<VirtualClassroomAttendance, $this> */
    public function classroomAttendances(): HasMany
    {
        return $this->hasMany(VirtualClassroomAttendance::class);
    }

    /** @return HasMany<LearningOfflineSync, $this> */
    public function offlineSyncs(): HasMany
    {
        return $this->hasMany(LearningOfflineSync::class);
    }

    /** @return HasMany<LearningCohortMembership, $this> */
    public function cohortMemberships(): HasMany
    {
        return $this->hasMany(LearningCohortMembership::class);
    }
}
