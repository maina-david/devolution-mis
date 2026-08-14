<?php

namespace App\Actions;

use App\Contracts\DocumentTextExtractor;
use App\Jobs\ExtractDocumentText;
use App\Models\AssessmentDocument;
use App\Models\DocumentLink;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSecurityScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreLinkedDocument
{
    public function __construct(private AuditLogger $auditLogger, private DocumentSecurityScanner $securityScanner, private DocumentTextExtractor $textExtractor) {}

    /** @param array{title: string, category: string, source_type: string, purpose: string, county_id: string|null, mime_type?: string} $data */
    public function handle(Model $subject, User $uploader, UploadedFile $file, array $data): AssessmentDocument
    {
        $inspection = $this->securityScanner->inspect($file);
        $path = $file->store('linked-documents/'.str($subject->getMorphClass())->slug().'/'.$subject->getKey());
        if ($path === false) {
            throw new RuntimeException(__('linked-documents.errors.store_failed'));
        }

        try {
            $document = DB::transaction(function () use ($subject, $uploader, $file, $data, $inspection, $path): AssessmentDocument {
                $ocrStatus = $inspection['status'] === 'clean' ? 'pending' : 'blocked';
                $mimeType = $data['mime_type'] ?? (string) $file->getMimeType();
                $document = AssessmentDocument::create(['assessment_id' => null, 'county_id' => $data['county_id'], 'title' => $data['title'], 'category' => $data['category'], 'source_type' => $data['source_type'], 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $mimeType, 'size_bytes' => $file->getSize(), 'content_checksum' => $inspection['checksum'], 'scan_status' => $inspection['status'], 'ocr_status' => $ocrStatus, 'document_date' => today(), 'uploaded_by' => $uploader->id]);
                $version = $document->versions()->create(['version_number' => 1, 'storage_disk' => config('filesystems.default'), 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $mimeType, 'size_bytes' => (int) $file->getSize(), 'content_checksum' => $inspection['checksum'], 'scan_status' => $inspection['status'], 'scan_details' => $inspection['details'], 'scanned_at' => now(), 'ocr_status' => $ocrStatus, 'change_summary' => __('linked-documents.initial_upload'), 'uploaded_by' => $uploader->id]);
                $document->update(['current_version_id' => $version->id]);
                DocumentLink::create(['assessment_document_id' => $document->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => (string) $subject->getKey(), 'purpose' => $data['purpose'], 'created_by' => $uploader->id]);
                if ($inspection['status'] === 'clean' && $this->textExtractor->supports($version)) {
                    ExtractDocumentText::dispatch($version->id, false, $uploader->id, 'upload');
                } elseif ($inspection['status'] === 'clean') {
                    $document->update(['ocr_status' => 'not_supported']);
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }
        $this->auditLogger->record($uploader, $document, 'document.linked_uploaded', __('linked-documents.audit.uploaded', ['title' => $document->title]), $data['county_id'], ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'purpose' => $data['purpose'], 'checksum' => $inspection['checksum'], 'scan_status' => $inspection['status']]);

        return $document;
    }
}
