<?php

namespace App\Actions;

use App\Models\RetentionSchedule;
use App\Models\RetentionScheduleApproval;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class ReviewRetentionSchedule
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array{decision: string, decision_reason: string} $attributes */
    public function handle(RetentionSchedule $retentionSchedule, User $actor, array $attributes): RetentionSchedule
    {
        return DB::transaction(function () use ($retentionSchedule, $actor, $attributes): RetentionSchedule {
            $schedule = RetentionSchedule::query()->lockForUpdate()->findOrFail($retentionSchedule->id);
            $approval = RetentionScheduleApproval::query()->where('retention_schedule_id', $schedule->id)->lockForUpdate()->firstOrFail();
            abort_unless($schedule->status === 'submitted' && $approval->status === 'submitted', 409, __('data-governance.retention_only_submitted'));
            abort_if($approval->submitted_by === $actor->id, 403, __('data-governance.retention_submitter_cannot_review'));
            $checksum = $this->canonicalJson->checksum($schedule->approvalSnapshot());
            abort_unless(hash_equals($approval->snapshot_checksum, $checksum), 409, __('data-governance.retention_changed_after_submission'));

            $approved = $attributes['decision'] === 'approved';
            $approval->update([
                'reviewed_by' => $actor->id,
                'status' => $attributes['decision'],
                'decision' => $attributes['decision'],
                'decision_reason' => $attributes['decision_reason'],
                'reviewed_at' => now(),
            ]);
            $schedule->update([
                'approved_by' => $approved ? $actor->id : null,
                'status' => $attributes['decision'],
                'approved_at' => $approved ? now() : null,
                'effective_from' => $approved ? now() : null,
            ]);
            $this->auditLogger->record($actor, $approval, 'privacy.retention-schedule.'.$attributes['decision'], "Retention schedule {$schedule->code} {$attributes['decision']} after independent review.", metadata: ['schedule_id' => $schedule->id, 'snapshot_checksum' => $checksum]);

            return $schedule->refresh();
        });
    }
}
