<?php

namespace App\Actions;

use App\Models\AssessmentCriterion;
use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishAssessmentScorecardVersion
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    public function handle(AssessmentScorecardVersion $scorecardVersion, User $publisher): AssessmentScorecardVersion
    {
        return DB::transaction(function () use ($scorecardVersion, $publisher): AssessmentScorecardVersion {
            $scorecard = AssessmentScorecard::query()->lockForUpdate()->findOrFail($scorecardVersion->assessment_scorecard_id);
            $version = AssessmentScorecardVersion::query()
                ->with('functions.thematicAreas.standards.criteria.evidenceRequirements')
                ->findOrFail($scorecardVersion->id);
            abort_unless($version->status === 'draft', 409, __('assessment-configuration.errors.draft_only'));
            $this->validateStructure($version);

            $publishedAt = now();
            $scorecard->versions()->where('status', 'published')->update(['status' => 'retired', 'effective_to' => $publishedAt]);
            $version->update([
                'status' => 'published',
                'checksum' => $this->canonicalJson->checksum($this->snapshot($version)),
                'effective_from' => $version->effective_from ?? $publishedAt,
                'published_by' => $publisher->id,
                'published_at' => $publishedAt,
            ]);

            return $version->refresh();
        }, attempts: 3);
    }

    private function validateStructure(AssessmentScorecardVersion $version): void
    {
        if ($version->functions->where('function_type', 'devolved')->count() !== 14) {
            throw ValidationException::withMessages(['functions' => __('assessment-configuration.errors.fourteen_functions')]);
        }

        $this->assertWeight($version->functions->sum(fn ($function) => (float) $function->weight), 'functions');
        foreach ($version->functions as $function) {
            $this->assertWeight($function->thematicAreas->sum(fn ($theme) => (float) $theme->weight), "function {$function->code} thematic areas");
            foreach ($function->thematicAreas as $theme) {
                $this->assertWeight($theme->standards->sum(fn ($standard) => (float) $standard->weight), "thematic area {$theme->code} standards");
                foreach ($theme->standards as $standard) {
                    $this->assertWeight($standard->criteria->sum(fn ($criterion) => (float) $criterion->weight), "standard {$standard->code} criteria");
                    if ($standard->criteria->contains(fn ($criterion) => $criterion->is_mandatory && $criterion->evidenceRequirements->where('is_mandatory', true)->isEmpty())) {
                        throw ValidationException::withMessages(['functions' => __('assessment-configuration.errors.mandatory_evidence', ['standard' => $standard->code])]);
                    }
                }
            }
        }
    }

    private function assertWeight(float $weight, string $scope): void
    {
        if (abs($weight - 100.0) > 0.0001) {
            throw ValidationException::withMessages(['functions' => __('assessment-configuration.errors.weight_total', ['scope' => $scope, 'weight' => $weight])]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(AssessmentScorecardVersion $version): array
    {
        return [
            'version' => $version->version,
            'calculation_method' => $version->calculation_method,
            'mcda_configuration' => $version->mcda_configuration,
            'performance_thresholds' => $version->performance_thresholds,
            'functions' => $this->functionSnapshots($version),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function functionSnapshots(AssessmentScorecardVersion $version): array
    {
        $snapshots = [];
        foreach ($version->functions->sortBy('sequence') as $function) {
            $snapshots[] = ['code' => $function->code, 'name' => $function->name, 'type' => $function->function_type, 'weight' => $function->weight, 'thematic_areas' => $this->thematicSnapshots($function)];
        }

        return $snapshots;
    }

    /** @return list<array<string, mixed>> */
    private function thematicSnapshots(AssessmentFunction $function): array
    {
        $snapshots = [];
        foreach ($function->thematicAreas->sortBy('sequence') as $theme) {
            $snapshots[] = ['code' => $theme->code, 'name' => $theme->name, 'weight' => $theme->weight, 'standards' => $this->standardSnapshots($theme)];
        }

        return $snapshots;
    }

    /** @return list<array<string, mixed>> */
    private function standardSnapshots(AssessmentThematicArea $theme): array
    {
        $snapshots = [];
        foreach ($theme->standards->sortBy('sequence') as $standard) {
            $snapshots[] = ['code' => $standard->code, 'name' => $standard->name, 'norm_reference' => $standard->norm_reference, 'weight' => $standard->weight, 'criteria' => $this->criterionSnapshots($standard)];
        }

        return $snapshots;
    }

    /** @return list<array<string, mixed>> */
    private function criterionSnapshots(AssessmentStandard $standard): array
    {
        $snapshots = [];
        foreach ($standard->criteria->sortBy('sequence') as $criterion) {
            $snapshots[] = ['code' => $criterion->code, 'name' => $criterion->name, 'weight' => $criterion->weight, 'maximum_score' => $criterion->maximum_score, 'scoring_method' => $criterion->scoring_method, 'formula' => $criterion->formula, 'thresholds' => $criterion->thresholds, 'is_mandatory' => $criterion->is_mandatory, 'evidence_requirements' => $this->evidenceSnapshots($criterion)];
        }

        return $snapshots;
    }

    /** @return list<array<string, mixed>> */
    private function evidenceSnapshots(AssessmentCriterion $criterion): array
    {
        $snapshots = [];
        foreach ($criterion->evidenceRequirements->sortBy('code') as $requirement) {
            $snapshots[] = ['code' => $requirement->code, 'name' => $requirement->name, 'minimum_documents' => $requirement->minimum_documents, 'allowed_categories' => $requirement->allowed_categories, 'accepted_mime_types' => $requirement->accepted_mime_types, 'requires_verification' => $requirement->requires_verification, 'is_mandatory' => $requirement->is_mandatory];
        }

        return $snapshots;
    }
}
