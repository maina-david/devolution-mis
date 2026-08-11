<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\DocumentLegalHold;
use App\Models\User;
use App\Services\AuditLogger;

class PlaceDocumentLegalHold
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{reference: string, reason: string, authority: string} $attributes */
    public function handle(AssessmentDocument $document, User $actor, array $attributes): DocumentLegalHold
    {
        abort_unless($actor->canAccessCounty($document->county), 403);
        $hold = $document->legalHolds()->create([...$attributes, 'placed_by' => $actor->id, 'placed_at' => now()]);
        $this->auditLogger->record($actor, $hold, 'document.legal_hold_placed', "Legal hold {$hold->reference} placed.", $document->county_id, ['authority' => $hold->authority]);

        return $hold;
    }
}
