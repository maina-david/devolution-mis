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
            throw ValidationException::withMessages(['score' => 'Only an independently verified score may be overridden.']);
        }
        if ($score < 0 || $score > (float) $result->criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => "Score must be between 0 and {$result->criterion->maximum_score}."]);
        }
        if (mb_strlen(trim($reason)) < 20) {
            throw ValidationException::withMessages(['reason' => 'A substantive override reason of at least 20 characters is required.']);
        }
        $result->update(['override_score' => $score, 'override_reason' => $reason, 'overridden_by' => $actor->id]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_overridden', "Criterion {$result->criterion->code} score overridden.", $result->assessment->county_id, ['previous_score' => $result->verified_score, 'override_score' => $score, 'reason' => $reason]);

        return $result;
    }
}
