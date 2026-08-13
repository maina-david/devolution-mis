<?php

namespace App\Actions;

use App\Contracts\DocumentTextExtractor;
use App\Jobs\ExtractDocumentText;
use App\Models\AssessmentDocument;
use App\Models\DocumentFolder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSecurityScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreRepositoryDocument
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DocumentSecurityScanner $securityScanner,
        private DocumentTextExtractor $textExtractor,
    ) {}

    /** @param array{title: string, category: string, source_type: string, description?: string|null, document_date?: string|null, tags?: string|null} $data */
    public function handle(DocumentFolder $folder, User $uploader, UploadedFile $file, array $data): AssessmentDocument
    {
        $inspection = $this->securityScanner->inspect($file);
        $path = $file->store('repository/'.($folder->county_id ?? 'national').'/'.$folder->id);
        if ($path === false) {
            throw new RuntimeException(__('document-repository.errors.store_failed'));
        }

        try {
            $document = DB::transaction(function () use ($folder, $uploader, $file, $data, $inspection, $path): AssessmentDocument {
                $ocrStatus = $inspection['status'] === 'clean' ? 'pending' : 'blocked';
                $mimeType = (string) $file->getMimeType();
                $document = AssessmentDocument::create([
                    'assessment_id' => null,
                    'county_id' => $folder->county_id,
                    'folder_id' => $folder->id,
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'source_type' => $data['source_type'],
                    'description' => $data['description'] ?? null,
                    'document_date' => $data['document_date'] ?? today(),
                    'tags' => collect(explode(',', $data['tags'] ?? ''))->map(fn (string $tag): string => trim($tag))->filter()->unique()->values()->all(),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size_bytes' => $file->getSize(),
                    'content_checksum' => $inspection['checksum'],
                    'scan_status' => $inspection['status'],
                    'ocr_status' => $ocrStatus,
                    'uploaded_by' => $uploader->id,
                ]);
                $version = $document->versions()->create([
                    'version_number' => 1,
                    'storage_disk' => config('filesystems.default'),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'size_bytes' => (int) $file->getSize(),
                    'content_checksum' => $inspection['checksum'],
                    'scan_status' => $inspection['status'],
                    'scan_details' => $inspection['details'],
                    'scanned_at' => now(),
                    'ocr_status' => $ocrStatus,
                    'change_summary' => __('document-repository.version.initial_upload'),
                    'uploaded_by' => $uploader->id,
                ]);
                $document->update(['current_version_id' => $version->id]);
                if ($inspection['status'] === 'clean' && $this->textExtractor->supports($version)) {
                    ExtractDocumentText::dispatch($version->id, false, $uploader->id, 'repository_upload');
                } elseif ($inspection['status'] === 'clean') {
                    $document->update(['ocr_status' => 'not_supported']);
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }

        $this->auditLogger->record($uploader, $document, 'document.repository.uploaded', __('document-repository.audit.document_uploaded', ['title' => $document->title, 'folder' => $folder->name]), $folder->county_id, ['folder_id' => $folder->id, 'checksum' => $inspection['checksum'], 'scan_status' => $inspection['status']]);

        return $document;
    }
}
