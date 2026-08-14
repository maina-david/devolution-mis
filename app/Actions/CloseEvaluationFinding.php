<?php

namespace App\Actions;

use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CloseEvaluationFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(EvaluationFinding $finding, User $actor, string $note): EvaluationFinding
    {
        abort_unless($actor->programmeRole()->hasNationalScope(), 403);
        $actions = $finding->actions()->with('updates')->get();
        if ($actions->isNotEmpty()) {
            abort_unless((float) $actions->sum('weight_percentage') === 100.0, 409, __('evaluation-findings.errors.closure_action_weight'));
            abort_if($actions->contains(fn (EvaluationFindingAction $action): bool => $action->status !== 'completed' || (float) $action->progress_percentage !== 100.0), 409, __('evaluation-findings.errors.closure_actions_complete'));
            $actionActorIds = [$finding->created_by];
            foreach ($actions as $action) {
                foreach ($action->updates as $update) {
                    $actionActorIds[] = $update->submitted_by;
                    if ($update->verified_by !== null) {
                        $actionActorIds[] = $update->verified_by;
                    }
                }
            }
            abort_if(in_array($actor->id, array_unique($actionActorIds), true), 409, __('evaluation-findings.errors.closure_separation'));
        }
        $latest = $finding->updates()->where('status', 'verified')->latest('verified_at')->first();
        if ($actions->isEmpty()) {
            abort_unless($latest instanceof EvaluationFindingUpdate && (float) $latest->progress_percentage === 100.0, 409, __('evaluation-findings.errors.closure_verified_response'));
            abort_if(in_array($actor->id, [$finding->created_by, $latest->submitted_by, $latest->verified_by], true), 409, __('evaluation-findings.errors.closure_separation'));
        }
        $finding = DB::transaction(function () use ($finding, $actor, $note): EvaluationFinding {
            $locked = EvaluationFinding::query()->lockForUpdate()->findOrFail($finding->id);
            abort_unless($locked->status === 'open', 409, __('evaluation-findings.errors.finding_not_open'));
            $locked->update(['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now(), 'closure_note' => $note]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $finding, 'evaluation.finding.closed', __('evaluation-findings.audit.closed', ['reference' => $finding->reference]), $finding->county_id, ['closure_note' => $note]);

        return $finding;
    }
}
