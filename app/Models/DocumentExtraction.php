<?php

namespace App\Models;

use Database\Factories\DocumentExtractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $document_version_id
 * @property string $status
 * @property string|null $engine
 * @property string $language
 * @property string|null $extracted_text
 * @property string|null $text_checksum_sha256
 * @property int $character_count
 * @property int|null $page_count
 * @property int $attempt_count
 * @property string|null $error_code
 * @property string|null $error_detail
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['document_version_id', 'status', 'engine', 'language', 'extracted_text', 'text_checksum_sha256', 'character_count', 'page_count', 'attempt_count', 'error_code', 'error_detail', 'metadata', 'started_at', 'completed_at'])]
class DocumentExtraction extends Model
{
    /** @use HasFactory<DocumentExtractionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending', 'character_count' => 0, 'attempt_count' => 0];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return HasMany<DocumentExtractionAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(DocumentExtractionAttempt::class)->orderByDesc('attempt_number');
    }
}
