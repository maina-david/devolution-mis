<?php

namespace App\Actions;

use App\Contracts\DocumentTextExtractor;
use App\Enums\UserRole;
use App\Jobs\ExtractDocumentText;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\CriterionEvidenceRequirement;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\DocumentSecurityScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreAssessmentEvidence
{
    public function __construct(private AuditLogger $auditLogger, private DocumentSecurityScanner $securityScanner, private DocumentTextExtractor $textExtractor) {}

    /** @param array{title: string, category: string, source_type: string, assessment_criterion_id?: string|null, criterion_evidence_requirement_id?: string|null} $data */
    public function handle(Assessment $assessment, User $uploader, UploadedFile $file, array $data): AssessmentDocument
    {
        $this->validateRequirementLink($assessment, $file, $data);
        $inspection = $this->securityScanner->inspect($file);
        $path = $file->store("assessment-evidence/{$assessment->county_id}/{$assessment->id}");

        if ($path === false) {
            throw new RuntimeException(__('assessment-record.errors.evidence_store_failed'));
        }

        try {
            $ocrStatus = $inspection['status'] === 'clean' ? 'pending' : 'blocked';
            $document = $assessment->documents()->create([
                'county_id' => $assessment->county_id,
                'assessment_criterion_id' => $data['assessment_criterion_id'] ?? null,
                'criterion_evidence_requirement_id' => $data['criterion_evidence_requirement_id'] ?? null,
                'title' => $data['title'],
                'category' => $data['category'],
                'source_type' => $data['source_type'],
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'content_checksum' => $inspection['checksum'],
                'scan_status' => $inspection['status'],
                'ocr_status' => $ocrStatus,
                'document_date' => today(),
                'uploaded_by' => $uploader->id,
            ]);
            $version = $document->versions()->create([
                'version_number' => 1,
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
                'change_summary' => __('document-repository.version.initial_upload'),
                'uploaded_by' => $uploader->id,
            ]);
            $document->update(['current_version_id' => $version->id]);
            if ($inspection['status'] === 'clean' && $this->textExtractor->supports($version)) {
                ExtractDocumentText::dispatch($version->id, false, $uploader->id, 'upload');
            } elseif ($inspection['status'] === 'clean') {
                $document->update(['ocr_status' => 'not_supported']);
            }
        } catch (Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }

        $assessors = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::Assessor->value))->whereHas('assignedCounties', fn ($query) => $query->whereKey($assessment->county_id))->get();
        Notification::send($assessors, new ProgrammeAlert(__('assessment-record.notifications.evidence_uploaded_title'), __('assessment-record.notifications.evidence_uploaded_message', ['title' => $data['title'], 'county' => $assessment->county->name, 'cycle' => $assessment->cycle]), 'evidence'));
        $this->auditLogger->record($uploader, $document, 'evidence.uploaded', __('assessment-record.audit.evidence_uploaded', ['title' => $data['title']]), $assessment->county_id, ['category' => $data['category'], 'source_type' => $data['source_type'], 'checksum' => $inspection['checksum'], 'scan_status' => $inspection['status']]);

        return $document;
    }

    /** @param array{title: string, category: string, source_type: string, assessment_criterion_id?: string|null, criterion_evidence_requirement_id?: string|null} $data */
    private function validateRequirementLink(Assessment $assessment, UploadedFile $file, array $data): void
    {
        $requirementId = $data['criterion_evidence_requirement_id'] ?? null;
        if ($requirementId === null) {
            return;
        }

        $requirement = CriterionEvidenceRequirement::query()->with('criterion.standard.thematicArea.function')->findOrFail($requirementId);
        $criterion = $requirement->criterion;
        abort_unless($criterion->id === ($data['assessment_criterion_id'] ?? null), 422, __('assessment-record.errors.evidence_criterion_mismatch'));
        abort_unless($criterion->standard->thematicArea->function->assessment_scorecard_version_id === $assessment->assessment_scorecard_version_id, 422, __('assessment-record.errors.evidence_scorecard_mismatch'));
        abort_unless(in_array($data['category'], $requirement->allowed_categories, true), 422, __('assessment-record.errors.evidence_category'));
        abort_unless(in_array((string) $file->getMimeType(), $requirement->accepted_mime_types, true), 422, __('assessment-record.errors.evidence_mime'));
    }
}
