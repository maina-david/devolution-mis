<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class SubmitCriterionScore
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, AssessmentCriterion $criterion, User $actor, float $score, string $rationale): AssessmentCriterionResult
    {
        abort_unless($criterion->standard->thematicArea->function->assessment_scorecard_version_id === $assessment->assessment_scorecard_version_id, 422, 'The criterion is not part of this assessment scorecard.');
        if ($score < 0 || $score > (float) $criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => "Score must be between 0 and {$criterion->maximum_score}."]);
        }
        $result = $assessment->criterionResults()->updateOrCreate(['assessment_criterion_id' => $criterion->id], ['submitted_score' => $score, 'submission_rationale' => $rationale, 'scored_by' => $actor->id, 'verified_score' => null, 'verified_by' => null, 'verified_at' => null]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_scored', "Criterion {$criterion->code} score submitted.", $assessment->county_id, ['score' => $score]);

        return $result;
    }
}
