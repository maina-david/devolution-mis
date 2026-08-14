<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ScoreAssessment->value) && $actor->canAccessCounty($assessment->county), 403, __('assessment-record.errors.criterion_scoring_unauthorized'));
        abort_unless($criterion->standard->thematicArea->function->assessment_scorecard_version_id === $assessment->assessment_scorecard_version_id, 422, __('assessment-record.errors.criterion_scorecard_mismatch'));
        if ($score < 0 || $score > (float) $criterion->maximum_score) {
            throw ValidationException::withMessages(['score' => __('assessment-record.errors.criterion_score_range', ['maximum' => $criterion->maximum_score])]);
        }
        $result = $assessment->criterionResults()->updateOrCreate(['assessment_criterion_id' => $criterion->id], ['submitted_score' => $score, 'submission_rationale' => $rationale, 'scored_by' => $actor->id, 'verified_score' => null, 'verified_by' => null, 'verified_at' => null]);
        $this->auditLogger->record($actor, $result, 'assessment.criterion_scored', __('assessment-record.audit.criterion_scored', ['criterion' => $criterion->code]), $assessment->county_id, ['score' => $score]);

        return $result;
    }
}
