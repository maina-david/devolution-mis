<?php

namespace App\Actions;

use App\Models\DataMigrationBatch;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewHistoricalDataMigration
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(DataMigrationBatch $batch, User $actor, string $decision, string $notes): DataMigrationBatch
    {
        return DB::transaction(function () use ($batch, $actor, $decision, $notes): DataMigrationBatch {
            $locked = DataMigrationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless(in_array($locked->status, ['validated', 'validation_failed'], true), 409, 'Only a validated migration batch can be reviewed.');
            abort_if($locked->submitted_by === $actor->id, 403, 'The migration submitter cannot review the same batch.');
            if ($decision === 'approve') {
                abort_unless($locked->status === 'validated' && $locked->invalid_rows === 0, 409, 'Resolve every validation exception before approval.');
            }

            $locked->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $this->auditLogger->record($actor, $locked, 'data_migration.'.$locked->status, "Historical data migration {$locked->status}.", null, ['file_checksum' => $locked->file_checksum, 'valid_rows' => $locked->valid_rows, 'invalid_rows' => $locked->invalid_rows]);

            return $locked->refresh();
        });
    }
}
