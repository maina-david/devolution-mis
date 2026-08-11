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
            abort_unless($activity->status === 'submitted', 409, 'Only submitted processing activities can be reviewed.');
            abort_if($activity->submitted_by === $reviewer->id, 403, 'The submitter cannot independently review this processing activity.');

            if ($attributes['decision'] === 'approved') {
                abort_unless($activity->retentionSchedule?->status === 'approved', 409, 'An approved retention schedule is required.');
                abort_if($activity->dataAsset->contains_sensitive_personal_data && $activity->dpia_status !== 'completed', 409, 'A completed DPIA is required for sensitive personal data processing.');
                abort_if($activity->cross_border_transfer && blank($activity->transfer_safeguards), 409, 'Documented transfer safeguards are required.');
            }

            $activity->update(['status' => $attributes['decision'], 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'risk_summary' => trim(($activity->risk_summary ? $activity->risk_summary."\n\n" : '').'Independent review: '.$attributes['review_note'])]);
            $this->auditLogger->record($reviewer, $activity, 'privacy.processing-activity.reviewed', "Processing activity {$activity->reference} {$attributes['decision']}.", metadata: ['decision' => $attributes['decision']]);

            return $activity->refresh();
        });
    }
}
