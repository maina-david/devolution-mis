<?php

namespace App\Actions;

use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateAssessmentScorecardVersion
{
    /** @param array<string, mixed> $data */
    public function handle(AssessmentScorecard $scorecard, array $data): AssessmentScorecardVersion
    {
        return DB::transaction(function () use ($scorecard, $data): AssessmentScorecardVersion {
            $lockedScorecard = AssessmentScorecard::query()->lockForUpdate()->findOrFail($scorecard->id);
            $version = $lockedScorecard->versions()->create([
                ...Arr::only($data, ['change_notes', 'calculation_method', 'mcda_configuration', 'performance_thresholds']),
                'version' => ((int) $lockedScorecard->versions()->withTrashed()->max('version')) + 1,
                'status' => 'draft',
            ]);

            foreach ($data['functions'] as $functionData) {
                $function = $version->functions()->create(Arr::except($functionData, ['thematic_areas']));
                foreach ($functionData['thematic_areas'] as $thematicData) {
                    $thematicArea = $function->thematicAreas()->create(Arr::except($thematicData, ['standards']));
                    foreach ($thematicData['standards'] as $standardData) {
                        $standard = $thematicArea->standards()->create(Arr::except($standardData, ['criteria']));
                        foreach ($standardData['criteria'] as $criterionData) {
                            $criterion = $standard->criteria()->create(Arr::except($criterionData, ['evidence_requirements']));
                            $criterion->evidenceRequirements()->createMany($criterionData['evidence_requirements']);
                        }
                    }
                }
            }

            return $version->load('functions.thematicAreas.standards.criteria.evidenceRequirements');
        }, attempts: 3);
    }
}
