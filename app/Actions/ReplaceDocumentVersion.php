<?php

namespace App\Actions;

use App\Contracts\DocumentTextExtractor;
use App\Jobs\ExtractDocumentText;
use App\Models\AssessmentDocument;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSecurityScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReplaceDocumentVersion
{
    public function __construct(private DocumentSecurityScanner $securityScanner, private AuditLogger $auditLogger, private DocumentTextExtractor $textExtractor) {}

    public function handle(AssessmentDocument $document, User $actor, UploadedFile $file, string $changeSummary): DocumentVersion
    {
        abort_unless($actor->canAccessCounty($document->county), 403);
        abort_if($document->hasActiveLegalHold(), 409, __('evidence.version_errors.legal_hold'));

        $inspection = $this->securityScanner->inspect($file);
        $path = $file->store("assessment-evidence/{$document->county_id}/{$document->assessment_id}/versions");
        if ($path === false) {
            throw new RuntimeException(__('evidence.version_errors.store_failed'));
        }

        try {
            $version = DB::transaction(function () use ($document, $actor, $file, $changeSummary, $inspection, $path): DocumentVersion {
                $locked = AssessmentDocument::query()->lockForUpdate()->findOrFail($document->id);
                $versionNumber = ((int) $locked->versions()->max('version_number')) + 1;
                $ocrStatus = $inspection['status'] === 'clean' ? 'pending' : 'blocked';
                $version = $locked->versions()->create([
                    'version_number' => $versionNumber,
                    'storage_disk' => config('filesystems.default'),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => (int) $file->getSize(),
                    'content_checksum' => $inspection['checksum'],
                    'scan_status' => $inspection['status'],
                    'scan_details' => $inspection['details'],
                    'scanned_at' => now(),
                    'ocr_status' => $ocrStatus,
                    'change_summary' => $changeSummary,
                    'uploaded_by' => $actor->id,
                ]);
                $locked->update([
                    'current_version_id' => $version->id,
                    'version' => $versionNumber,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'content_checksum' => $inspection['checksum'],
                    'scan_status' => $inspection['status'],
                    'ocr_status' => $ocrStatus,
                    'verification_status' => 'pending',
                ]);

                return $version;
            });
        } catch (Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }

        $this->auditLogger->record($actor, $document, 'document.version_created', __('evidence.audit.version_created', ['version' => $version->version_number]), $document->county_id, ['checksum' => $inspection['checksum'], 'scan_status' => $inspection['status'], 'change_summary' => $changeSummary]);
        if ($inspection['status'] === 'clean' && $this->textExtractor->supports($version)) {
            ExtractDocumentText::dispatch($version->id, false, $actor->id, 'version_replacement');
        } elseif ($inspection['status'] === 'clean') {
            $document->update(['ocr_status' => 'not_supported']);
        }

        return $version;
    }
}
