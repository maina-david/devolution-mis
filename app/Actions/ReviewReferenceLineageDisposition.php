<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\ReferenceLineageDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewReferenceLineageDisposition
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ReferenceLineageDisposition $disposition, User $actor, string $decision, string $notes): ReferenceLineageDisposition
    {
        abort_unless($actor->can(ProgrammePermission::ApproveReferenceData->value), 403, __('migration.lineage_errors.review_unauthorized'));

        return DB::transaction(function () use ($actor, $decision, $disposition, $notes): ReferenceLineageDisposition {
            $locked = ReferenceLineageDisposition::query()->lockForUpdate()->findOrFail($disposition->id);
            abort_unless($locked->status === 'proposed', 409, __('migration.lineage_errors.proposed_only'));
            abort_if($locked->proposed_by === $actor->id, 403, __('migration.lineage_errors.proposer_review'));
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $locked->update(['status' => $status, 'reviewed_by' => $actor->id, 'review_notes' => $notes, 'reviewed_at' => now()]);
            $this->auditLogger->record($actor, $locked, "reference_lineage.{$status}", __('migration.lineage_audit.reviewed', ['reference' => $locked->reference, 'status' => __('migration.lineage_status.'.$status)]), metadata: ['decision_checksum' => $locked->decision_checksum, 'review_notes' => $notes]);

            return $locked->refresh();
        });
    }
}
