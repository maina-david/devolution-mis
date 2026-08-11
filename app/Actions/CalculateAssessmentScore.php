<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculateAssessmentScore
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, User $actor): Assessment
    {
        $assessment = DB::transaction(function () use ($assessment, $actor): Assessment {
            $locked = Assessment::query()->lockForUpdate()->with('scorecardVersion.functions.thematicAreas.standards.criteria.evidenceRequirements')->findOrFail($assessment->id);
            if ($locked->scorecardVersion === null) {
                throw ValidationException::withMessages(['assessment' => 'A governed scorecard version is required before calculation.']);
            }

            $required = 0;
            $satisfied = 0;
            $total = 0.0;
            foreach ($locked->scorecardVersion->functions as $function) {
                foreach ($function->thematicAreas as $theme) {
                    foreach ($theme->standards as $standard) {
                        foreach ($standard->criteria as $criterion) {
                            [$criterionRequired, $criterionSatisfied] = $this->evidenceCompleteness($locked, $criterion);
                            $required += $criterionRequired;
                            $satisfied += $criterionSatisfied;
                            $result = $locked->criterionResults()->where('assessment_criterion_id', $criterion->id)->first();
                            if ($result === null || ($result->verified_score === null && $result->override_score === null)) {
                                throw ValidationException::withMessages(['score' => "Criterion {$criterion->code} requires a verified score."]);
                            }

                            $effective = (float) ($result->override_score ?? $result->verified_score);
                            $weighted = ($effective / (float) $criterion->maximum_score)
                                * (float) $criterion->weight / 100
                                * (float) $standard->weight / 100
                                * (float) $theme->weight / 100
                                * (float) $function->weight;
                            $total += $weighted;
                            $result->update(['weighted_score' => $weighted, 'calculation_snapshot' => ['effective_score' => $effective, 'maximum_score' => (float) $criterion->maximum_score, 'weights' => ['criterion' => (float) $criterion->weight, 'standard' => (float) $standard->weight, 'theme' => (float) $theme->weight, 'function' => (float) $function->weight]]]);
                        }
                    }
                }
            }

            $completeness = $required === 0 ? 100.0 : ($satisfied / $required) * 100;
            if ($completeness < 100) {
                throw ValidationException::withMessages(['evidence' => "Mandatory evidence is {$completeness}% complete."]);
            }
            $locked->update(['status' => AssessmentStatus::Assessed, 'score' => round($total, 2), 'completeness_percentage' => 100, 'assessor_id' => $actor->id, 'assessed_at' => now()]);

            return $locked;
        }, attempts: 3);

        $this->auditLogger->record($actor, $assessment, 'assessment.score_calculated', 'Assessment score calculated from the released scorecard.', $assessment->county_id, ['score' => $assessment->score]);

        return $assessment;
    }

    /** @return array{int, int} */
    private function evidenceCompleteness(Assessment $assessment, AssessmentCriterion $criterion): array
    {
        $required = 0;
        $satisfied = 0;
        foreach ($criterion->evidenceRequirements->where('is_mandatory', true) as $requirement) {
            $required += $requirement->minimum_documents;
            $count = $assessment->documents()->where('criterion_evidence_requirement_id', $requirement->id)->where('verification_status', 'verified')->count();
            $satisfied += min($count, $requirement->minimum_documents);
        }

        return [$required, $satisfied];
    }
}
