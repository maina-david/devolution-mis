<?php

namespace App\Jobs;

use App\Contracts\DocumentTextExtractor;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionAttempt;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ExtractDocumentText implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public string $documentVersionId, public bool $force = false, public ?string $initiatedById = null, public string $triggerSource = 'upload')
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->documentVersionId;
    }

    public function handle(DocumentTextExtractor $extractor, AuditLogger $auditLogger): void
    {
        $version = DocumentVersion::query()->with('document')->findOrFail($this->documentVersionId);
        $document = $version->document;
        if ($document->current_version_id !== $version->id || $version->scan_status !== 'clean') {
            return;
        }

        $execution = DB::transaction(function () use ($version): ?array {
            $current = DocumentExtraction::query()->withTrashed()->lockForUpdate()->where('document_version_id', $version->id)->first();
            if ($current?->status === 'completed' && ! $this->force) {
                return null;
            }
            if ($current?->trashed()) {
                $current->restore();
            }

            $current ??= DocumentExtraction::query()->create([
                'document_version_id' => $version->id,
                'language' => ReferenceCatalogue::defaultOcrLanguage(),
            ]);
            $current->update([
                'status' => 'processing',
                'attempt_count' => $current->attempt_count + 1,
                'started_at' => now(),
                'completed_at' => null,
                'error_code' => null,
                'error_detail' => null,
            ]);

            $initiator = $this->initiatedById ? User::query()->find($this->initiatedById) : null;
            $attempt = $current->attempts()->create([
                'document_version_id' => $version->id,
                'attempt_number' => $current->attempt_count,
                'initiated_by' => $initiator?->id,
                'initiated_by_name' => $initiator?->name,
                'trigger_source' => $this->triggerSource,
                'language' => ReferenceCatalogue::defaultOcrLanguage(),
                'started_at' => $current->started_at,
            ]);

            return [$current, $attempt];
        });
        if (! is_array($execution)) {
            return;
        }

        /** @var DocumentExtraction $extraction */
        [$extraction, $attempt] = $execution;

        try {
            $result = $extractor->extract($version);
        } catch (Throwable $exception) {
            $this->finishAttempt($attempt, ['status' => 'failed', 'engine' => null, 'language' => ReferenceCatalogue::defaultOcrLanguage(), 'text' => null, 'page_count' => null, 'error_code' => 'job_exception', 'error_detail' => Str::limit($exception->getMessage(), 1000), 'metadata' => []]);
            throw $exception;
        }

        $text = $result['text'];
        $extraction->update([
            'status' => $result['status'],
            'engine' => $result['engine'],
            'language' => $result['language'],
            'extracted_text' => $text,
            'text_checksum_sha256' => $text !== null ? hash('sha256', $text) : null,
            'character_count' => $text !== null ? Str::length($text) : 0,
            'page_count' => $result['page_count'],
            'error_code' => $result['error_code'],
            'error_detail' => $result['error_detail'],
            'metadata' => $result['metadata'],
            'completed_at' => now(),
        ]);
        $this->finishAttempt($attempt, $result);
        $document->refresh();
        if ($document->current_version_id === $version->id) {
            $document->update(['ocr_status' => $result['status']]);
        }
        $auditLogger->record(null, $document, 'document.text_extraction_completed', "Document text extraction finished with status {$result['status']}.", $document->county_id, [
            'version_id' => $version->id,
            'engine' => $result['engine'],
            'status' => $result['status'],
            'character_count' => $text !== null ? Str::length($text) : 0,
            'error_code' => $result['error_code'],
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'trigger_source' => $attempt->trigger_source,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $version = DocumentVersion::query()->with('document')->find($this->documentVersionId);
        if (! $version instanceof DocumentVersion) {
            return;
        }

        DocumentExtraction::query()->updateOrCreate(
            ['document_version_id' => $version->id],
            ['status' => 'failed', 'error_code' => 'job_failed', 'error_detail' => Str::limit($exception->getMessage(), 1000), 'completed_at' => now()],
        );
        DocumentExtractionAttempt::query()
            ->where('document_version_id', $version->id)
            ->where('status', 'processing')
            ->latest('attempt_number')
            ->first()
            ?->update(['status' => 'failed', 'error_code' => 'job_failed', 'error_detail' => Str::limit($exception->getMessage(), 1000), 'completed_at' => now()]);
        if ($version->document->current_version_id === $version->id) {
            $version->document->update(['ocr_status' => 'failed']);
        }
    }

    /**
     * @param  array{status: string, engine: string|null, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>}  $result
     */
    private function finishAttempt(DocumentExtractionAttempt $attempt, array $result): void
    {
        $completedAt = now();
        $text = $result['text'];
        $attempt->update([
            'status' => $result['status'],
            'engine' => $result['engine'],
            'language' => $result['language'],
            'text_checksum_sha256' => $text !== null ? hash('sha256', $text) : null,
            'character_count' => $text !== null ? Str::length($text) : 0,
            'page_count' => $result['page_count'],
            'error_code' => $result['error_code'],
            'error_detail' => $result['error_detail'],
            'metadata' => $result['metadata'],
            'completed_at' => $completedAt,
            'duration_ms' => (int) $attempt->started_at->diffInMilliseconds($completedAt),
        ]);
    }
}
