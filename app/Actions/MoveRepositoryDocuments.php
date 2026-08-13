<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\DocumentFolder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class MoveRepositoryDocuments
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param list<AssessmentDocument> $documents */
    public function handle(array $documents, DocumentFolder $folder, User $actor): void
    {
        DB::transaction(function () use ($documents, $folder, $actor): void {
            foreach ($documents as $document) {
                abort_if($document->county_id !== $folder->county_id, 422, __('document-repository.errors.document_scope_mismatch', ['title' => $document->title]));
                abort_if($document->hasActiveLegalHold(), 409, __('document-repository.errors.document_hold_move', ['title' => $document->title]));
                $document->update(['folder_id' => $folder->id]);
                $this->auditLogger->record($actor, $document, 'document.repository.moved', __('document-repository.audit.document_moved', ['title' => $document->title, 'folder' => $folder->name]), $document->county_id, ['folder_id' => $folder->id]);
            }
        });
    }
}
