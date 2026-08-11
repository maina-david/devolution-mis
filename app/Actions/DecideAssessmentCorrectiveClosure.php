<?php

namespace App\Actions;

use App\Models\AssessmentCorrectivePlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class DecideAssessmentCorrectiveClosure
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCorrectivePlan $plan, User $actor, string $decision, string $reason): AssessmentCorrectivePlan
    {
        abort_unless($actor->canAccessCounty($plan->county), 403);
        abort_if(in_array($actor->id, [$plan->submitted_by, $plan->reviewed_by], true), 409, 'Corrective closure requires a decision-maker independent of the submitter and plan reviewer.');
        $plan = DB::transaction(function () use ($plan, $actor, $decision, $reason): AssessmentCorrectivePlan {
            $locked = AssessmentCorrectivePlan::query()->lockForUpdate()->findOrFail($plan->id);
            abort_unless($locked->status === 'closure_requested', 409, 'Closure has not been requested for this plan.');
            $locked->update(['status' => $decision === 'closed' ? 'closed' : 'active', 'closed_by' => $actor->id, 'closed_at' => $decision === 'closed' ? now() : null, 'closure_requested_at' => $decision === 'closed' ? $locked->closure_requested_at : null, 'closure_decision' => $reason]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $plan, "assessment.corrective_plan_{$decision}", "Corrective plan {$plan->reference} closure decision recorded.", $plan->county_id, ['decision' => $reason]);

        return $plan;
    }
}
