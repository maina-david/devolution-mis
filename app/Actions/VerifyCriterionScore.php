<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentCriterionResult;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class VerifyCriterionScore
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCriterionResult $result, User $actor, float $score, string $rationale): AssessmentCriterionResult
    {
        abort_unless($actor->can(ProgrammePermission::ReviewAssessment->value) && $actor->canAccessCounty($result->assessment->county), 403, __('assessment-record.errors.criterion_verification_unauthorized'));

        if ($result->scored_by === $actor->id) {
            throw ValidationException::withMessages(['actor' => __('assessment-record.errors.criterion_independent_verifier')]);
        }
        if ($score < 0 || $score > (float) $result->criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => __('assessment-record.errors.criterion_score_range', ['maximum' => $result->criterion->maximum_score])]);
        }
        $result->update(['verified_score' => $score, 'verification_rationale' => $rationale, 'verified_by' => $actor->id, 'verified_at' => now()]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_verified', __('assessment-record.audit.criterion_verified', ['criterion' => $result->criterion->code]), $result->assessment->county_id, ['score' => $score]);

        return $result;
    }
}
