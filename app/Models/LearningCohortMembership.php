<?php

namespace App\Models;

use Database\Factories\LearningCohortMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['learning_cohort_id', 'learning_enrollment_id', 'added_by', 'joined_at'])]
class LearningCohortMembership extends Model
{
    /** @use HasFactory<LearningCohortMembershipFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(LearningCohort::class, 'learning_cohort_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }
}
