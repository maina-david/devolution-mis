<?php

namespace App\Models;

use Database\Factories\DocumentExtractionAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $document_extraction_id
 * @property string $document_version_id
 * @property int $attempt_number
 * @property string|null $initiated_by
 * @property string|null $initiated_by_name
 * @property string $trigger_source
 * @property string $status
 * @property string|null $engine
 * @property string $language
 * @property string|null $text_checksum_sha256
 * @property int $character_count
 * @property int|null $page_count
 * @property string|null $error_code
 * @property string|null $error_detail
 * @property array<string, mixed>|null $metadata
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 */
#[Fillable(['document_extraction_id', 'document_version_id', 'attempt_number', 'initiated_by', 'initiated_by_name', 'trigger_source', 'status', 'engine', 'language', 'text_checksum_sha256', 'character_count', 'page_count', 'error_code', 'error_detail', 'metadata', 'started_at', 'completed_at', 'duration_ms'])]
class DocumentExtractionAttempt extends Model
{
    /** @use HasFactory<DocumentExtractionAttemptFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'processing', 'character_count' => 0];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DocumentExtraction, $this> */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class, 'document_extraction_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
