<?php

namespace App\Actions;

use App\Models\DocumentDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class DecideDocumentDisposition
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(DocumentDisposition $disposition, User $actor, string $decision, string $reason): DocumentDisposition
    {
        abort_unless($actor->canAccessCounty($disposition->document->county), 403);
        abort_if($disposition->requested_by === $actor->id, 409, __('evidence.disposition.errors.reviewer_separation'));

        $disposition = DB::transaction(function () use ($disposition, $actor, $decision, $reason): DocumentDisposition {
            $locked = DocumentDisposition::query()->lockForUpdate()->findOrFail($disposition->id);
            abort_unless($locked->status === 'pending', 409, __('evidence.disposition.errors.pending_required'));
            $locked->update(['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision_reason' => $reason, 'status' => $decision]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $disposition, "document.disposition_{$decision}", __('evidence.disposition.audit.decided', ['decision' => __("evidence.disposition.statuses.{$decision}")]), $disposition->document->county_id, ['decision_reason' => $reason]);

        return $disposition;
    }
}
