<?php

namespace App\Actions;

use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class VerifyEvaluationFindingActionUpdate
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(EvaluationFindingActionUpdate $update, User $actor, string $decision, string $note): EvaluationFindingActionUpdate
    {
        $action = $update->action;
        $finding = $action->finding;
        abort_unless($actor->programmeRole()->hasNationalScope() || ($finding->county_id !== null && $actor->canAccessCounty($finding->county)), 403);
        abort_if(in_array($actor->id, [$finding->created_by, $action->created_by, $update->submitted_by], true), 409, 'Action progress verification must be independent of issuance, action creation and submission.');

        $update = DB::transaction(function () use ($update, $actor, $decision, $note, $finding): EvaluationFindingActionUpdate {
            $locked = EvaluationFindingActionUpdate::query()->lockForUpdate()->findOrFail($update->id);
            abort_unless($locked->status === 'pending_verification', 409, 'This action update has already been decided.');
            $locked->update(['status' => $decision, 'verified_by' => $actor->id, 'verified_at' => now(), 'decision_note' => $note]);
            if ($decision === 'verified') {
                $action = EvaluationFindingAction::query()->lockForUpdate()->findOrFail($locked->evaluation_finding_action_id);
                $action->update(['progress_percentage' => $locked->progress_percentage, 'status' => $locked->progress_percentage >= 100 ? 'completed' : 'in_progress']);
                $weightedProgress = (float) EvaluationFindingAction::query()->where('evaluation_finding_id', $finding->id)->sum(DB::raw('progress_percentage * weight_percentage / 100'));
                EvaluationFinding::query()->whereKey($finding->id)->update(['progress_percentage' => round($weightedProgress, 2)]);
            }

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $update, "evaluation.finding_action.progress_{$decision}", "Action progress {$decision}.", $finding->county_id, ['decision_note' => $note]);

        return $update;
    }
}
