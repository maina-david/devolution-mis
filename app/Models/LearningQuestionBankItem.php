<?php

namespace App\Models;

use Database\Factories\LearningQuestionBankItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $variant_group
 * @property string $difficulty
 * @property list<string> $tags
 */
#[Fillable(['learning_question_bank_id', 'learning_quiz_question_id', 'variant_group', 'difficulty', 'tags', 'sequence'])]
class LearningQuestionBankItem extends Model
{
    /** @use HasFactory<LearningQuestionBankItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    /** @return BelongsTo<LearningQuestionBank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(LearningQuestionBank::class, 'learning_question_bank_id');
    }

    /** @return BelongsTo<LearningQuizQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(LearningQuizQuestion::class, 'learning_quiz_question_id');
    }
}
