<?php

namespace App\Models;

use Database\Factories\LearningQuizQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['learning_lesson_id', 'question', 'options', 'correct_option', 'explanation', 'points', 'sequence'])]
class LearningQuizQuestion extends Model
{
    /** @use HasFactory<LearningQuizQuestionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['options' => 'array', 'points' => 'decimal:2'];
    }

    /** @return BelongsTo<LearningLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }
}
