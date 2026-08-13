<?php

namespace App\Actions;

use App\Models\AssessmentCorrectivePlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewAssessmentCorrectivePlan
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCorrectivePlan $plan, User $actor, string $decision, string $note): AssessmentCorrectivePlan
    {
        abort_unless($actor->canAccessCounty($plan->county), 403);
        abort_if($plan->submitted_by === $actor->id, 409, __('assessment-record.corrective.errors.independent_plan_reviewer'));
        $plan = DB::transaction(function () use ($plan, $actor, $decision, $note): AssessmentCorrectivePlan {
            $locked = AssessmentCorrectivePlan::query()->lockForUpdate()->findOrFail($plan->id);
            abort_unless(in_array($locked->status, ['submitted', 'returned'], true), 409, __('assessment-record.corrective.errors.plan_not_reviewable'));
            $locked->update(['status' => $decision === 'activate' ? 'active' : 'returned', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $plan, "assessment.corrective_plan_{$decision}", __('assessment-record.corrective.audit.plan_reviewed', ['reference' => $plan->reference]), $plan->county_id, ['review_note' => $note]);

        return $plan;
    }
}
