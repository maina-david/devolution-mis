<?php

namespace App\Actions;

use App\Models\AssessmentCriterionResult;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class OverrideCriterionScore
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCriterionResult $result, User $actor, float $score, string $reason): AssessmentCriterionResult
    {
        if ($result->verified_score === null) {
            throw ValidationException::withMessages(['score' => __('assessment-record.errors.override_verified_only')]);
        }
        if ($score < 0 || $score > (float) $result->criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => __('assessment-record.errors.override_range', ['maximum' => $result->criterion->maximum_score])]);
        }
        if (mb_strlen(trim($reason)) < 20) {
            throw ValidationException::withMessages(['reason' => __('assessment-record.errors.override_reason')]);
        }
        $result->update(['override_score' => $score, 'override_reason' => $reason, 'overridden_by' => $actor->id]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_overridden', __('assessment-record.audit.criterion_overridden', ['criterion' => $result->criterion->code]), $result->assessment->county_id, ['previous_score' => $result->verified_score, 'override_score' => $score, 'reason' => $reason]);

        return $result;
    }
}
