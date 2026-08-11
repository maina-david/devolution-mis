<?php

namespace App\Models;

use Database\Factories\CitizenCaseAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $id @property string $title @property string $original_name @property string $path @property string $mime_type @property int $size_bytes @property string $checksum_sha256 @property string $source_type @property string $scan_status @property string $ocr_status */
#[Fillable(['citizen_case_id', 'citizen_case_message_id', 'title', 'original_name', 'path', 'mime_type', 'size_bytes', 'checksum_sha256', 'source_type', 'scan_status', 'scan_details', 'ocr_status', 'uploaded_by'])]
class CitizenCaseAttachment extends Model
{
    /** @use HasFactory<CitizenCaseAttachmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'scan_details' => 'array'];
    }

    /** @return BelongsTo<CitizenCase, $this> */
    public function citizenCase(): BelongsTo
    {
        return $this->belongsTo(CitizenCase::class);
    }

    /** @return BelongsTo<CitizenCaseMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CitizenCaseMessage::class, 'citizen_case_message_id');
    }
}
