<?php

namespace App\Actions;

use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class VerifyAssessmentCorrectiveUpdate
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCorrectiveUpdate $update, User $actor, string $decision, string $note): AssessmentCorrectiveUpdate
    {
        $plan = $update->action->plan;
        abort_unless($actor->canAccessCounty($plan->county), 403);
        abort_if($update->submitted_by === $actor->id, 409, 'Progress evidence must be independently verified.');
        $update = DB::transaction(function () use ($update, $actor, $decision, $note): AssessmentCorrectiveUpdate {
            $locked = AssessmentCorrectiveUpdate::query()->lockForUpdate()->findOrFail($update->id);
            abort_unless($locked->status === 'pending_verification', 409, 'This progress update has already been decided.');
            $locked->update(['status' => $decision, 'verified_by' => $actor->id, 'verified_at' => now(), 'decision_note' => $note]);
            if ($decision === 'verified') {
                $action = AssessmentCorrectiveAction::query()->lockForUpdate()->findOrFail($locked->assessment_corrective_action_id);
                $action->update(['progress_percentage' => $locked->progress_percentage, 'status' => (float) $locked->progress_percentage >= 100 ? 'completed' : 'in_progress']);
            }

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $update, "assessment.corrective_progress_{$decision}", "Corrective progress {$decision}.", $plan->county_id, ['decision_note' => $note]);

        return $update;
    }
}
