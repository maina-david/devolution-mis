<?php

namespace App\Models;

use Database\Factories\LearningQuestionBankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['learning_course_id', 'code', 'title', 'description', 'selection_count', 'randomize_questions', 'randomize_options', 'version', 'status', 'checksum', 'created_by', 'published_at'])]
class LearningQuestionBank extends Model
{
    /** @use HasFactory<LearningQuestionBankFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['randomize_questions' => 'boolean', 'randomize_options' => 'boolean', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LearningQuestionBankItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LearningQuestionBankItem::class);
    }
}
