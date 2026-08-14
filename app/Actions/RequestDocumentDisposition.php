<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\DocumentDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RequestDocumentDisposition
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{reason: string, authority_reference: string, scheduled_for: string} $attributes */
    public function handle(AssessmentDocument $document, User $actor, array $attributes): DocumentDisposition
    {
        abort_unless($actor->canAccessCounty($document->county), 403);
        abort_if($document->retention_until === null, 409, __('evidence.disposition.errors.retention_due_required'));
        abort_if($document->hasActiveLegalHold(), 409, __('evidence.disposition.errors.legal_hold_request'));
        $scheduledFor = CarbonImmutable::parse($attributes['scheduled_for'])->startOfDay();
        abort_if($scheduledFor->lessThan($document->retention_until->startOfDay()), 409, __('evidence.disposition.errors.retention_not_expired_request'));

        $disposition = DB::transaction(function () use ($document, $actor, $attributes, $scheduledFor): DocumentDisposition {
            $lockedDocument = AssessmentDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_if($lockedDocument->dispositions()->whereIn('status', ['pending', 'approved', 'executing', 'execution_failed'])->exists(), 409, __('evidence.disposition.errors.open_request_exists'));

            return $lockedDocument->dispositions()->create([
                'requested_by' => $actor->id,
                'action' => 'secure_destroy',
                'reason' => $attributes['reason'],
                'authority_reference' => $attributes['authority_reference'],
                'retention_due_at' => $lockedDocument->retention_until,
                'scheduled_for' => $scheduledFor,
                'status' => 'pending',
            ]);
        });
        $this->auditLogger->record($actor, $disposition, 'document.disposition_requested', __('evidence.disposition.audit.requested', ['title' => $document->title]), $document->county_id, ['authority_reference' => $disposition->authority_reference, 'scheduled_for' => $disposition->scheduled_for->toDateString()]);

        return $disposition;
    }
}
