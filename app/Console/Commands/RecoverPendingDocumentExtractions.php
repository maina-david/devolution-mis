<?php

namespace App\Console\Commands;

use App\Jobs\ExtractDocumentText;
use App\Models\AssessmentDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:recover-extractions {--limit=250 : Maximum current document versions to queue}')]
#[Description('Queue clean current document versions whose text extraction is pending or retryable.')]
class RecoverPendingDocumentExtractions extends Command
{
    public function handle(): int
    {
        $queued = 0;
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $retryBefore = now()->subMinutes((int) config('repository.extraction.retry_after_minutes', 30));

        AssessmentDocument::query()
            ->where('scan_status', 'clean')
            ->whereNotNull('current_version_id')
            ->where(function ($query) use ($retryBefore): void {
                $query->whereIn('ocr_status', ['pending', 'waiting_dependency'])
                    ->orWhere(function ($query) use ($retryBefore): void {
                        $query->where('ocr_status', 'failed')->where('updated_at', '<=', $retryBefore);
                    });
            })
            ->oldest('updated_at')
            ->limit($limit)
            ->get(['current_version_id'])
            ->each(function (AssessmentDocument $document) use (&$queued): void {
                ExtractDocumentText::dispatch((string) $document->current_version_id, false, null, 'scheduled_recovery');
                $queued++;
            });

        $this->components->info("Queued {$queued} document extraction(s).");

        return self::SUCCESS;
    }
}
