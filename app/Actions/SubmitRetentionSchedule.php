<?php

namespace App\Actions;

use App\Models\RetentionSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class SubmitRetentionSchedule
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, User $actor): RetentionSchedule
    {
        return DB::transaction(function () use ($attributes, $actor): RetentionSchedule {
            $schedule = RetentionSchedule::create([...$attributes, 'status' => 'submitted']);
            $checksum = $this->canonicalJson->checksum($schedule->approvalSnapshot());
            $approval = $schedule->approval()->create([
                'submitted_by' => $actor->id,
                'snapshot_checksum' => $checksum,
                'submitted_at' => now(),
            ]);
            $this->auditLogger->record($actor, $approval, 'privacy.retention-schedule.submitted', "Retention schedule {$schedule->code} submitted for independent approval.", metadata: ['schedule_id' => $schedule->id, 'snapshot_checksum' => $checksum]);

            return $schedule->refresh();
        });
    }
}
