<?php

namespace App\Actions;

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
        if (! in_array($assessment->status->value, ['assessed', 'approved'], true)) {
            throw ValidationException::withMessages(['appeal' => 'Appeals may only be lodged against assessed or approved results.']);
        }
        $appeal = $assessment->appeals()->create(['assessment_criterion_id' => $criterionId, 'appellant_id' => $actor->id, 'grounds' => $grounds, 'requested_remedy' => $requestedRemedy, 'submitted_at' => now()]);
        $this->auditLogger->record($actor, $appeal, 'assessment.appeal_submitted', 'Assessment appeal submitted.', $assessment->county_id, ['criterion_id' => $criterionId]);

        return $appeal;
    }
}
