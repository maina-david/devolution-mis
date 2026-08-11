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
        abort_if($plan->submitted_by === $actor->id, 409, 'The plan submitter cannot independently review the plan.');
        $plan = DB::transaction(function () use ($plan, $actor, $decision, $note): AssessmentCorrectivePlan {
            $locked = AssessmentCorrectivePlan::query()->lockForUpdate()->findOrFail($plan->id);
            abort_unless(in_array($locked->status, ['submitted', 'returned'], true), 409, 'Only a submitted or returned plan can be reviewed.');
            $locked->update(['status' => $decision === 'activate' ? 'active' : 'returned', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $plan, "assessment.corrective_plan_{$decision}", "Corrective plan {$plan->reference} review recorded.", $plan->county_id, ['review_note' => $note]);

        return $plan;
    }
}
