<?php

namespace App\Actions;

use App\Models\ProcessingActivity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewProcessingActivity
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{decision: string, review_note: string} $attributes */
    public function handle(ProcessingActivity $activity, User $reviewer, array $attributes): ProcessingActivity
    {
        return DB::transaction(function () use ($activity, $reviewer, $attributes): ProcessingActivity {
            $activity = ProcessingActivity::query()->with(['dataAsset', 'retentionSchedule'])->lockForUpdate()->findOrFail($activity->id);
            abort_unless($activity->status === 'submitted', 409, __('data-governance.processing.errors.only_submitted'));
            abort_if($activity->submitted_by === $reviewer->id, 403, __('data-governance.processing.errors.submitter_cannot_review'));

            if ($attributes['decision'] === 'approved') {
                abort_unless($activity->retentionSchedule?->status === 'approved', 409, __('data-governance.processing.errors.approved_retention_required'));
                abort_if($activity->dataAsset->contains_sensitive_personal_data && $activity->dpia_status !== 'completed', 409, __('data-governance.processing.errors.completed_dpia_required'));
                abort_if($activity->cross_border_transfer && blank($activity->transfer_safeguards), 409, __('data-governance.processing.errors.transfer_safeguards_required'));
            }

            $activity->update(['status' => $attributes['decision'], 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'risk_summary' => trim(($activity->risk_summary ? $activity->risk_summary."\n\n" : '').__('data-governance.processing.review_note', ['note' => $attributes['review_note']]))]);
            $this->auditLogger->record($reviewer, $activity, 'privacy.processing-activity.reviewed', __('data-governance.processing.audit.reviewed', ['reference' => $activity->reference, 'decision' => __("data-governance.processing.statuses.{$attributes['decision']}")]), metadata: ['decision' => $attributes['decision']]);

            return $activity->refresh();
        });
    }
}
