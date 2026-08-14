<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class SubmitAssessmentAppeal
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, User $actor, string $grounds, string $requestedRemedy, ?string $criterionId = null): AssessmentAppeal
    {
        abort_unless($actor->can(ProgrammePermission::SubmitAssessment->value) && $actor->canAccessCounty($assessment->county), 403, __('assessment-record.errors.appeal_submission_unauthorized'));

        if (! in_array($assessment->status->value, ['assessed', 'approved'], true)) {
            throw ValidationException::withMessages(['appeal' => __('assessment-record.errors.appeal_status_required')]);
        }
        $appeal = $assessment->appeals()->create(['assessment_criterion_id' => $criterionId, 'appellant_id' => $actor->id, 'grounds' => $grounds, 'requested_remedy' => $requestedRemedy, 'submitted_at' => now()]);
        $this->auditLogger->record($actor, $appeal, 'assessment.appeal_submitted', __('assessment-record.audit.appeal_submitted'), $assessment->county_id, ['criterion_id' => $criterionId]);

        return $appeal;
    }
}
