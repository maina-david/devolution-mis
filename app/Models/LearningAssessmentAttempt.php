<?php

namespace App\Models;

use Database\Factories\LearningAssessmentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|list<array<string, mixed>> $result_snapshot
 */
#[Fillable(['learning_enrollment_id', 'attempt_number', 'answers', 'result_snapshot', 'score', 'passed', 'submitted_at'])]
class LearningAssessmentAttempt extends Model
{
    /** @use HasFactory<LearningAssessmentAttemptFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['answers' => 'array', 'result_snapshot' => 'array', 'score' => 'decimal:2', 'passed' => 'boolean', 'submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }
}
