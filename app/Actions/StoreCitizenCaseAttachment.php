<?php

namespace App\Actions;

use App\Models\CitizenCase;
use App\Models\CitizenCaseAttachment;
use App\Models\CitizenCaseMessage;
use App\Models\User;
use App\Services\DocumentSecurityScanner;
use Illuminate\Http\UploadedFile;

class StoreCitizenCaseAttachment
{
    public function __construct(private DocumentSecurityScanner $scanner) {}

    public function handle(CitizenCase $case, UploadedFile $file, string $sourceType, ?User $uploader = null, ?CitizenCaseMessage $message = null): CitizenCaseAttachment
    {
        $scan = $this->scanner->inspect($file);
        $path = $file->store("citizen-cases/{$case->id}");
        abort_if($path === false, 500, 'The supporting document could not be stored.');

        return $case->attachments()->create(['citizen_case_message_id' => $message?->id, 'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'checksum_sha256' => $scan['checksum'], 'source_type' => $sourceType, 'scan_status' => $scan['status'] === 'clean' ? 'clean' : 'quarantined', 'scan_details' => $scan['details'], 'ocr_status' => $sourceType === 'scanned' && $scan['status'] === 'clean' ? 'pending' : 'not_required', 'uploaded_by' => $uploader?->id]);
    }
}
