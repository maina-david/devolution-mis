<?php

namespace App\Models;

use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $assessment_document_id
 * @property int $version_number
 * @property string $storage_disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $content_checksum
 * @property string $scan_status
 * @property string $ocr_status
 * @property string|null $change_summary
 * @property string|null $uploaded_by
 * @property Carbon $created_at
 * @property-read User|null $uploader
 * @property-read DocumentExtraction|null $extraction
 */
#[Fillable(['assessment_document_id', 'version_number', 'storage_disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'content_checksum', 'scan_status', 'scan_details', 'scanned_at', 'ocr_status', 'ocr_text', 'ocr_language', 'extraction_metadata', 'change_summary', 'uploaded_by'])]
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['scan_details' => 'array', 'scanned_at' => 'immutable_datetime', 'extraction_metadata' => 'array'];
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasOne<DocumentExtraction, $this> */
    public function extraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class);
    }

    public function extractionStatus(): string
    {
        $extraction = $this->getRelationValue('extraction');

        return $extraction instanceof DocumentExtraction ? $extraction->status : $this->ocr_status;
    }
}
