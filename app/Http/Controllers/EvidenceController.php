<?php

namespace App\Http\Controllers;

use App\Actions\DecideDocumentDisposition;
use App\Actions\ExecuteDocumentDisposition;
use App\Actions\PlaceDocumentLegalHold;
use App\Actions\ReplaceDocumentVersion;
use App\Actions\RequestDocumentDisposition;
use App\Actions\StoreAssessmentEvidence;
use App\Actions\VerifyAssessmentEvidence;
use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Http\Requests\DecideDocumentDispositionRequest;
use App\Http\Requests\ExecuteDocumentDispositionRequest;
use App\Http\Requests\ReleaseDocumentLegalHoldRequest;
use App\Http\Requests\ReplaceDocumentVersionRequest;
use App\Http\Requests\StoreDocumentDispositionRequest;
use App\Http\Requests\StoreDocumentLegalHoldRequest;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Requests\VerifyEvidenceRequest;
use App\Jobs\ExtractDocumentText;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\DocumentDisposition;
use App\Models\DocumentLegalHold;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentAccess;
use App\Services\DocumentIntegrityVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    /** @var list<string> */
    private const MEDIA_PREVIEW_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
    ];

    /** @var list<string> */
    private const STATIC_PREVIEW_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];

    public function __construct(private DocumentIntegrityVerifier $integrityVerifier, private DocumentAccess $documentAccess) {}

    public function preview(Request $request, AssessmentDocument $document, AuditLogger $auditLogger): StreamedResponse
    {
        abort_unless($this->documentAccess->allows($this->user($request), $document), 403);
        abort_unless($document->scan_status === 'clean', 423, 'This document is quarantined until its security scan passes.');
        abort_unless(Storage::exists($document->path), 404);
        abort_unless($this->hasValidIntegrity($document), 409, 'Document integrity verification failed.');
        if (! $this->isPreviewableMimeType((string) $document->mime_type)) {
            abort(415, 'Preview is not available for this file type.');
        }
        $auditLogger->record($this->user($request), $document, 'evidence.previewed', "Document previewed: {$document->title}.", $document->county_id);

        if ($this->isMediaMimeType((string) $document->mime_type)) {
            return $this->streamMedia($request, (string) config('filesystems.default'), $document->path, $document->original_name, (string) $document->mime_type);
        }

        return Storage::response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, AssessmentDocument $document, AuditLogger $auditLogger): StreamedResponse
    {
        abort_unless($this->documentAccess->allows($this->user($request), $document), 403);
        abort_unless($document->scan_status === 'clean', 423, 'This document is quarantined until its security scan passes.');
        abort_unless(Storage::exists($document->path), 404);
        abort_unless($this->hasValidIntegrity($document), 409, 'Document integrity verification failed.');
        $auditLogger->record($this->user($request), $document, 'evidence.downloaded', "Document downloaded: {$document->title}.", $document->county_id);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $downloadName = str($document->title)->slug()->append($extension ? ".{$extension}" : '')->toString();

        return Storage::download($document->path, $downloadName);
    }

    public function previewVersion(Request $request, AssessmentDocument $document, DocumentVersion $version, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeVersionAccess($request, $document, $version);
        abort_unless($this->isPreviewableMimeType($version->mime_type), 415, 'Preview is not available for this file type.');
        $auditLogger->record($this->user($request), $version, 'evidence.version_previewed', "Document version {$version->version_number} previewed: {$document->title}.", $document->county_id);

        if ($this->isMediaMimeType($version->mime_type)) {
            return $this->streamMedia($request, $version->storage_disk, $version->path, $version->original_name, $version->mime_type);
        }

        return Storage::disk($version->storage_disk)->response($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadVersion(Request $request, AssessmentDocument $document, DocumentVersion $version, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeVersionAccess($request, $document, $version);
        $auditLogger->record($this->user($request), $version, 'evidence.version_downloaded', "Document version {$version->version_number} downloaded: {$document->title}.", $document->county_id);

        return Storage::disk($version->storage_disk)->download($version->path, $version->original_name);
    }

    public function store(StoreEvidenceRequest $request, Assessment $assessment, StoreAssessmentEvidence $storeEvidence): RedirectResponse
    {
        abort_unless($this->user($request)->canAccessCounty($assessment->county), 403);
        abort_if(in_array($assessment->status, [AssessmentStatus::Assessed, AssessmentStatus::Approved, AssessmentStatus::Published]), 409, 'Evidence is locked after assessment.');
        $storeEvidence->handle($assessment, $this->user($request), $request->file('document'), [
            'title' => $request->string('title')->toString(),
            'category' => $request->string('category')->toString(),
            'source_type' => $request->string('source_type')->toString(),
            'assessment_criterion_id' => $request->validated('assessment_criterion_id'),
            'criterion_evidence_requirement_id' => $request->validated('criterion_evidence_requirement_id'),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evidence uploaded securely.']);

        return back();
    }

    public function verify(VerifyEvidenceRequest $request, AssessmentDocument $document, VerifyAssessmentEvidence $verifyEvidence): RedirectResponse
    {
        abort_unless($this->user($request)->canAccessCounty($document->county), 403);
        $verifyEvidence->handle($document, $request->validated('status'), $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evidence verification recorded.']);

        return back();
    }

    public function update(UpdateDocumentRequest $request, AssessmentDocument $document, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($this->user($request)->canAccessCounty($document->county), 403);
        abort_if($document->hasActiveLegalHold() && $request->validated('retention_until') !== $document->retention_until?->toDateString(), 409, 'Retention cannot be changed while a legal hold is active.');
        $document->update([
            ...$request->safe()->only(['title', 'category', 'description', 'document_date', 'retention_until']),
            'tags' => $request->string('tags')->explode(',')->map(fn (string $tag): string => str($tag)->trim()->toString())->filter()->values()->all(),
        ]);
        $auditLogger->record($this->user($request), $document, 'evidence.metadata_updated', "Document metadata updated: {$document->title}.", $document->county_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document metadata updated.']);

        return back();
    }

    public function destroy(Request $request, AssessmentDocument $document, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::UploadEvidence->value);
        abort_unless($this->user($request)->canAccessCounty($document->county), 403);
        abort_if($document->hasActiveLegalHold(), 409, 'A document under legal hold cannot be archived.');
        $auditLogger->record($this->user($request), $document, 'evidence.archived', "Document archived: {$document->title}.", $document->county_id);
        $document->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document archived.']);

        return back();
    }

    public function replace(ReplaceDocumentVersionRequest $request, AssessmentDocument $document, ReplaceDocumentVersion $replace): RedirectResponse
    {
        $replace->handle($document, $this->user($request), $request->file('document'), $request->string('change_summary')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'A new immutable document version was uploaded.']);

        return back();
    }

    public function extract(Request $request, AssessmentDocument $document): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageRecords->value);
        abort_unless($this->user($request)->canAccessCounty($document->county), 403);
        abort_unless($document->scan_status === 'clean' && $document->current_version_id !== null, 409, 'Only a clean current document version can be processed.');
        $document->update(['ocr_status' => 'pending']);
        ExtractDocumentText::dispatch((string) $document->current_version_id, true, $this->user($request)->id, 'manual_retry');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document text extraction queued.']);

        return back();
    }

    public function placeLegalHold(StoreDocumentLegalHoldRequest $request, AssessmentDocument $document, PlaceDocumentLegalHold $placeHold): RedirectResponse
    {
        $placeHold->handle($document, $this->user($request), [
            'reference' => $request->string('reference')->toString(),
            'reason' => $request->string('reason')->toString(),
            'authority' => $request->string('authority')->toString(),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legal hold placed.']);

        return back();
    }

    public function releaseLegalHold(ReleaseDocumentLegalHoldRequest $request, AssessmentDocument $document, DocumentLegalHold $legalHold, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($this->user($request)->canAccessCounty($document->county), 403);
        abort_unless($legalHold->assessment_document_id === $document->id && $legalHold->released_at === null, 404);
        abort_if($legalHold->placed_by === $this->user($request)->id, 409, 'The officer who placed a legal hold cannot release it.');
        $validated = $request->validated();
        $legalHold->update(['released_by' => $this->user($request)->id, 'released_at' => now(), 'release_reason' => $validated['release_reason']]);
        $auditLogger->record($this->user($request), $legalHold, 'document.legal_hold_released', "Legal hold {$legalHold->reference} released.", $document->county_id, ['reason' => $validated['release_reason']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Legal hold released.']);

        return back();
    }

    public function requestDisposition(StoreDocumentDispositionRequest $request, AssessmentDocument $document, RequestDocumentDisposition $requestDisposition): RedirectResponse
    {
        $requestDisposition->handle($document, $this->user($request), [
            'reason' => $request->string('reason')->toString(),
            'authority_reference' => $request->string('authority_reference')->toString(),
            'scheduled_for' => $request->date('scheduled_for')->toDateString(),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Controlled disposition submitted for independent review.']);

        return back();
    }

    public function decideDisposition(DecideDocumentDispositionRequest $request, AssessmentDocument $document, DocumentDisposition $disposition, DecideDocumentDisposition $decide): RedirectResponse
    {
        abort_unless($disposition->assessment_document_id === $document->id, 404);
        $decide->handle($disposition, $this->user($request), $request->string('decision')->toString(), $request->string('decision_reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Disposition review decision recorded.']);

        return back();
    }

    public function executeDisposition(ExecuteDocumentDispositionRequest $request, AssessmentDocument $document, DocumentDisposition $disposition, ExecuteDocumentDisposition $execute): RedirectResponse
    {
        abort_unless($disposition->assessment_document_id === $document->id, 404);
        $execute->handle($disposition, $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Controlled disposition executed and evidence retained.']);

        return redirect()->route('evidence.index');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function hasValidIntegrity(AssessmentDocument $document): bool
    {
        $version = $document->currentVersion;
        if ($version !== null) {
            return $version->path === $document->path
                && $this->integrityVerifier->matches($version->storage_disk, $version->path, $version->content_checksum);
        }

        return $this->integrityVerifier->matches((string) config('filesystems.default'), $document->path, $document->content_checksum);
    }

    private function authorizeVersionAccess(Request $request, AssessmentDocument $document, DocumentVersion $version): void
    {
        abort_unless($this->documentAccess->allows($this->user($request), $document), 403);
        abort_unless($version->assessment_document_id === $document->id, 404);
        abort_unless($version->scan_status === 'clean', 423, 'This document version is quarantined until its security scan passes.');
        abort_unless(Storage::disk($version->storage_disk)->exists($version->path), 404);
        abort_unless($this->integrityVerifier->matches($version->storage_disk, $version->path, $version->content_checksum), 409, 'Document version integrity verification failed.');
    }

    private function isPreviewableMimeType(string $mimeType): bool
    {
        return in_array($mimeType, [...self::STATIC_PREVIEW_MIME_TYPES, ...self::MEDIA_PREVIEW_MIME_TYPES], true);
    }

    private function isMediaMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::MEDIA_PREVIEW_MIME_TYPES, true);
    }

    private function streamMedia(Request $request, string $disk, string $path, string $originalName, string $mimeType): StreamedResponse
    {
        $storage = Storage::disk($disk);
        $size = $storage->size($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $request->header('Range');

        if ($range !== null) {
            abort_unless(preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) === 1, 416, "The requested media range is invalid: {$range}.");
            abort_if($matches[1] === '' && $matches[2] === '', 416, 'The requested media range is invalid.');

            if ($matches[1] === '') {
                $suffixLength = min((int) $matches[2], $size);
                abort_if($suffixLength < 1, 416, 'The requested media range is invalid.');
                $start = $size - $suffixLength;
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min((int) $matches[2], $end);
            }

            abort_if($start >= $size || $start > $end, 416, "The requested media range {$start}-{$end} is outside the {$size}-byte file.");
            $status = 206;
        }

        $length = $end - $start + 1;
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $originalName),
            'Content-Length' => (string) $length,
            'Content-Type' => $mimeType,
            'Content-Security-Policy' => "default-src 'none'; media-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($storage, $path, $start, $length): void {
            $stream = $storage->readStream($path);
            abort_unless(is_resource($stream), 404);

            try {
                if ($start > 0 && fseek($stream, $start) !== 0) {
                    $remainingSkip = $start;
                    while ($remainingSkip > 0 && ! feof($stream)) {
                        $skipped = fread($stream, min(8192, $remainingSkip));
                        if ($skipped === false || $skipped === '') {
                            break;
                        }
                        $remainingSkip -= strlen($skipped);
                    }
                }

                $remaining = $length;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(8192, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
            } finally {
                fclose($stream);
            }
        }, $status, $headers);
    }
}
