<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentAppeal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class DecideAssessmentAppeal
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentAppeal $appeal, User $actor, string $status, string $decision): AssessmentAppeal
    {
        abort_unless($actor->can(ProgrammePermission::ApproveAssessment->value) && $actor->canAccessCounty($appeal->assessment->county), 403, __('assessment-record.errors.appeal_decision_unauthorized'));

        if (! in_array($appeal->status, ['submitted', 'under_review'], true)) {
            throw ValidationException::withMessages(['decision' => __('assessment-record.errors.appeal_already_decided')]);
        }
        $appeal->update(['status' => $status, 'decision' => $decision, 'reviewer_id' => $actor->id, 'decided_at' => now()]);
        $this->auditLogger->record($actor, $appeal, 'assessment.appeal_decided', __('assessment-record.audit.appeal_decided', ['status' => __('assessment-record.appeal_statuses.'.$status)]), $appeal->assessment->county_id, ['decision' => $decision]);

        return $appeal;
    }
}
