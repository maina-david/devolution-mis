<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentResultPublication;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishAssessmentResult
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, User $actor): AssessmentResultPublication
    {
        $publication = DB::transaction(function () use ($assessment, $actor): AssessmentResultPublication {
            $locked = Assessment::query()->lockForUpdate()->with(['scorecardVersion.functions.thematicAreas.standards.criteria', 'criterionResults', 'findings', 'appeals', 'attestations'])->findOrFail($assessment->id);
            $this->validatePublication($locked);
            $profile = $this->functionProfile($locked);
            $calculation = $locked->criterionResults->sortBy('assessment_criterion_id')->map(fn ($result) => ['criterion_id' => $result->assessment_criterion_id, 'verified_score' => $result->verified_score, 'override_score' => $result->override_score, 'weighted_score' => $result->weighted_score, 'calculation_snapshot' => $result->calculation_snapshot])->values()->all();
            $snapshot = ['assessment_id' => $locked->id, 'cycle_id' => $locked->assessment_cycle_id, 'county_id' => $locked->county_id, 'scorecard_version_id' => $locked->assessment_scorecard_version_id, 'scorecard_checksum' => $locked->scorecardVersion->checksum, 'score' => $locked->score, 'performance_band' => $this->performanceBand($locked), 'function_profile' => $profile, 'calculation' => $calculation, 'attestation_checksums' => $locked->attestations->pluck('content_checksum')->sort()->values()->all()];
            $publication = AssessmentResultPublication::query()->create(['assessment_id' => $locked->id, 'assessment_cycle_id' => $locked->assessment_cycle_id, 'assessment_scorecard_version_id' => $locked->assessment_scorecard_version_id, 'county_id' => $locked->county_id, 'score' => $locked->score, 'performance_band' => $snapshot['performance_band'], 'function_profile' => $profile, 'calculation_snapshot' => $snapshot, 'checksum' => $this->canonicalJson->checksum($snapshot), 'published_by' => $actor->id, 'published_at' => now()]);
            $locked->update(['status' => AssessmentStatus::Published, 'published_by' => $actor->id, 'published_at' => now()]);

            return $publication;
        }, attempts: 3);

        $this->auditLogger->record($actor, $publication, 'assessment.result_published', __('assessment-record.audit.result_published'), $publication->county_id, ['checksum' => $publication->checksum, 'score' => $publication->score]);

        return $publication;
    }

    private function validatePublication(Assessment $assessment): void
    {
        if ($assessment->status !== AssessmentStatus::Approved || $assessment->assessment_cycle_id === null || $assessment->assessment_scorecard_version_id === null || $assessment->score === null) {
            throw ValidationException::withMessages(['publication' => __('assessment-record.errors.publication_approved_only')]);
        }
        if ($assessment->completeness_percentage < 100 || $assessment->attestation_status !== 'attested' || $assessment->attestations->whereNull('revoked_at')->isEmpty()) {
            throw ValidationException::withMessages(['publication' => __('assessment-record.errors.publication_evidence_attestation')]);
        }
        if ($assessment->findings->where('status', '!=', 'resolved')->isNotEmpty()) {
            throw ValidationException::withMessages(['publication' => __('assessment-record.errors.publication_findings')]);
        }
        if ($assessment->appeals->whereIn('status', ['submitted', 'under_review'])->isNotEmpty()) {
            throw ValidationException::withMessages(['publication' => __('assessment-record.errors.publication_appeals')]);
        }
        if ($assessment->criterionResults->contains(fn ($result) => $result->weighted_score === null || $result->calculation_snapshot === null)) {
            throw ValidationException::withMessages(['publication' => __('assessment-record.errors.publication_reproducible_results')]);
        }
    }

    /** @return list<array{function_id: string, code: string, name: string, weight: float, score: float, weighted_contribution: float}> */
    private function functionProfile(Assessment $assessment): array
    {
        $profile = [];
        foreach ($assessment->scorecardVersion->functions->sortBy('sequence') as $function) {
            $criterionIds = $function->thematicAreas->flatMap(fn ($theme) => $theme->standards->flatMap(fn ($standard) => $standard->criteria->pluck('id')));
            $contribution = (float) $assessment->criterionResults->whereIn('assessment_criterion_id', $criterionIds)->sum('weighted_score');
            $profile[] = ['function_id' => $function->id, 'code' => $function->code, 'name' => $function->name, 'weight' => (float) $function->weight, 'score' => (float) $function->weight > 0 ? round(($contribution / (float) $function->weight) * 100, 2) : 0.0, 'weighted_contribution' => round($contribution, 4)];
        }

        return $profile;
    }

    private function performanceBand(Assessment $assessment): string
    {
        $score = (float) $assessment->score;
        foreach ($assessment->scorecardVersion->performance_thresholds as $threshold) {
            if ($score >= (float) ($threshold['minimum'] ?? 0) && $score <= (float) ($threshold['maximum'] ?? 100)) {
                return (string) ($threshold['label'] ?? 'Unclassified');
            }
        }

        return 'Unclassified';
    }
}
