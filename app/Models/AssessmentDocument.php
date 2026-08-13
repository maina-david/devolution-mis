<?php

namespace App\Models;

use Database\Factories\AssessmentDocumentFactory;
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
 * @property string|null $assessment_id
 * @property string|null $county_id
 * @property string|null $folder_id
 * @property string $title
 * @property string $category
 * @property string $source_type
 * @property string $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $description
 * @property Carbon|null $document_date
 * @property Carbon|null $retention_until
 * @property list<string>|null $tags
 * @property string $verification_status
 * @property string|null $uploaded_by
 * @property string|null $content_checksum
 * @property string $scan_status
 * @property string $ocr_status
 * @property string $record_status
 * @property int $version
 * @property bool $active_legal_hold
 */
#[Fillable(['assessment_id', 'assessment_criterion_id', 'criterion_evidence_requirement_id', 'county_id', 'folder_id', 'category', 'source_type', 'title', 'path', 'current_version_id', 'original_name', 'mime_type', 'size_bytes', 'content_checksum', 'scan_status', 'ocr_status', 'security_classification', 'record_status', 'description', 'document_date', 'version', 'tags', 'retention_until', 'verification_status', 'uploaded_by'])]
class AssessmentDocument extends Model
{
    /** @use HasFactory<AssessmentDocumentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['verification_status' => 'pending', 'scan_status' => 'pending', 'ocr_status' => 'not_requested', 'security_classification' => 'official', 'record_status' => 'active'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'retention_until' => 'date',
            'tags' => 'array',
        ];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<AssessmentCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssessmentCriterion::class, 'assessment_criterion_id');
    }

    /** @return BelongsTo<CriterionEvidenceRequirement, $this> */
    public function evidenceRequirement(): BelongsTo
    {
        return $this->belongsTo(CriterionEvidenceRequirement::class, 'criterion_evidence_requirement_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<DocumentFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return HasMany<DocumentLegalHold, $this> */
    public function legalHolds(): HasMany
    {
        return $this->hasMany(DocumentLegalHold::class);
    }

    /** @return HasMany<DocumentDisposition, $this> */
    public function dispositions(): HasMany
    {
        return $this->hasMany(DocumentDisposition::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function hasActiveLegalHold(): bool
    {
        return $this->legalHolds()->whereNull('released_at')->exists();
    }
}
