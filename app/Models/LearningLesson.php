<?php

namespace App\Models;

use Database\Factories\LearningLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['learning_module_id', 'title', 'summary', 'content_type', 'content_body', 'content_url', 'mime_type', 'content_checksum', 'estimated_minutes', 'sequence', 'is_required', 'is_downloadable', 'metadata'])]
class LearningLesson extends Model
{
    /** @use HasFactory<LearningLessonFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_downloadable' => 'boolean', 'metadata' => 'array'];
    }

    /** @return array<string, mixed> */
    public function assetMetadata(): array
    {
        $rawMetadata = $this->getRawOriginal('metadata');
        if (! is_string($rawMetadata)) {
            return [];
        }

        $metadata = json_decode($rawMetadata, true);

        return is_array($metadata) ? $metadata : [];
    }

    /** @return BelongsTo<LearningModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /** @return HasMany<LearningQuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(LearningQuizQuestion::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
