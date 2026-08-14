<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\DataMigrationBatch;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewHistoricalDataMigration
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(DataMigrationBatch $batch, User $actor, string $decision, string $notes): DataMigrationBatch
    {
        abort_unless($actor->can(ProgrammePermission::ApproveReferenceData->value), 403, __('migration.errors.review_unauthorized'));

        return DB::transaction(function () use ($batch, $actor, $decision, $notes): DataMigrationBatch {
            $locked = DataMigrationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless(in_array($locked->status, ['validated', 'validation_failed'], true), 409, __('migration.review.validated_only'));
            abort_if($locked->submitted_by === $actor->id, 403, __('migration.review.submitter_review'));
            if ($decision === 'approve') {
                abort_unless($locked->status === 'validated' && $locked->invalid_rows === 0, 409, __('migration.review.validation_exceptions'));
            }

            $locked->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $this->auditLogger->record($actor, $locked, 'data_migration.'.$locked->status, __('migration.review.audit', ['status' => __('migration.lineage_status.'.$locked->status)]), null, ['file_checksum' => $locked->file_checksum, 'valid_rows' => $locked->valid_rows, 'invalid_rows' => $locked->invalid_rows]);

            return $locked->refresh();
        });
    }
}
