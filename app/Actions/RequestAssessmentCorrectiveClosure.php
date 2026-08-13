<?php

namespace App\Actions;

use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectivePlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RequestAssessmentCorrectiveClosure
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCorrectivePlan $plan, User $actor): AssessmentCorrectivePlan
    {
        abort_unless($actor->canAccessCounty($plan->county), 403);
        $plan = DB::transaction(function () use ($plan): AssessmentCorrectivePlan {
            $locked = AssessmentCorrectivePlan::query()->lockForUpdate()->with('actions')->findOrFail($plan->id);
            abort_unless($locked->status === 'active', 409, __('assessment-record.corrective.errors.active_closure_required'));
            abort_if($locked->actions->isEmpty() || $locked->actions->contains(fn (AssessmentCorrectiveAction $action): bool => $action->status !== 'completed'), 409, __('assessment-record.corrective.errors.verified_completion_required'));
            $locked->update(['status' => 'closure_requested', 'closure_requested_at' => now()]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $plan, 'assessment.corrective_closure_requested', __('assessment-record.corrective.audit.closure_requested', ['reference' => $plan->reference]), $plan->county_id);

        return $plan;
    }
}
