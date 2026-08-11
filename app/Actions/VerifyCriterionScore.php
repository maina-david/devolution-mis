<?php

namespace App\Actions;

use App\Models\AssessmentCriterionResult;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class VerifyCriterionScore
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentCriterionResult $result, User $actor, float $score, string $rationale): AssessmentCriterionResult
    {
        if ($result->scored_by === $actor->id) {
            throw ValidationException::withMessages(['actor' => 'A criterion score must be independently verified by a different user.']);
        }
        if ($score < 0 || $score > (float) $result->criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => "Score must be between 0 and {$result->criterion->maximum_score}."]);
        }
        $result->update(['verified_score' => $score, 'verification_rationale' => $rationale, 'verified_by' => $actor->id, 'verified_at' => now()]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_verified', "Criterion {$result->criterion->code} score independently verified.", $result->assessment->county_id, ['score' => $score]);

        return $result;
    }
}
