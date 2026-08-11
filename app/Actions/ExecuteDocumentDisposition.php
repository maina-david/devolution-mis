<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\DocumentDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentIntegrityVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExecuteDocumentDisposition
{
    public function __construct(private AuditLogger $auditLogger, private DocumentIntegrityVerifier $integrityVerifier) {}

    public function handle(DocumentDisposition $disposition, User $actor): DocumentDisposition
    {
        abort_unless($actor->canAccessCounty($disposition->document->county), 403);
        abort_if(in_array($actor->id, [$disposition->requested_by, $disposition->reviewed_by], true), 409, 'Disposition execution requires an officer independent of the requester and reviewer.');
        abort_if($disposition->document->hasActiveLegalHold(), 409, 'A document under legal hold cannot be disposed.');
        abort_if($disposition->scheduled_for->isFuture() || $disposition->retention_due_at->isFuture(), 409, 'The approved retention period has not expired.');

        DB::transaction(function () use ($disposition, $actor): void {
            $locked = DocumentDisposition::query()->lockForUpdate()->findOrFail($disposition->id);
            abort_unless(in_array($locked->status, ['approved', 'execution_failed'], true), 409, 'Only an approved or failed disposition can be executed.');
            $locked->update(['status' => 'executing', 'executed_by' => $actor->id, 'execution_started_at' => now(), 'execution_error' => null]);
        });

        try {
            $disposition->refresh();
            $document = AssessmentDocument::withTrashed()->with('versions')->findOrFail($disposition->assessment_document_id);
            $manifest = $this->manifest($document);

            foreach ($manifest as $object) {
                abort_unless(Storage::disk($object['disk'])->exists($object['path']), 409, "A retained object is missing: {$object['path']}.");
                abort_unless($this->integrityVerifier->matches($object['disk'], $object['path'], $object['checksum']), 409, "Integrity verification failed for retained object: {$object['path']}.");
            }
            foreach ($manifest as $object) {
                if (! Storage::disk($object['disk'])->delete($object['path']) || Storage::disk($object['disk'])->exists($object['path'])) {
                    throw new RuntimeException("Secure object deletion could not be confirmed: {$object['path']}.");
                }
            }

            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            DB::transaction(function () use ($disposition, $document, $manifest, $manifestJson): void {
                $locked = DocumentDisposition::query()->lockForUpdate()->findOrFail($disposition->id);
                abort_unless($locked->status === 'executing', 409, 'Disposition execution state changed unexpectedly.');
                $locked->update([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'object_manifest' => $manifest,
                    'manifest_checksum' => hash('sha256', $manifestJson),
                    'object_count' => count($manifest),
                    'total_bytes' => collect($manifest)->sum('size_bytes'),
                ]);
                $document->update(['record_status' => 'disposed']);
                $document->delete();
            });
            $disposition->refresh();
            $this->auditLogger->record($actor, $disposition, 'document.disposition_executed', "Controlled disposition executed for {$document->title}.", $document->county_id, ['manifest_checksum' => $disposition->manifest_checksum, 'object_count' => $disposition->object_count]);

            return $disposition;
        } catch (Throwable $exception) {
            DocumentDisposition::query()->whereKey($disposition->id)->where('status', 'executing')->update(['status' => 'execution_failed', 'execution_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    /** @return list<array{disk: string, path: string, checksum: string, size_bytes: int}> */
    private function manifest(AssessmentDocument $document): array
    {
        $objects = [];
        foreach ($document->versions as $version) {
            $key = "{$version->storage_disk}:{$version->path}";
            $objects[$key] = ['disk' => $version->storage_disk, 'path' => $version->path, 'checksum' => $version->content_checksum, 'size_bytes' => $version->size_bytes];
        }
        if ($objects === []) {
            $disk = (string) config('filesystems.default');
            $objects["{$disk}:{$document->path}"] = ['disk' => $disk, 'path' => $document->path, 'checksum' => (string) $document->content_checksum, 'size_bytes' => (int) $document->size_bytes];
        }
        ksort($objects);

        return array_values($objects);
    }
}
