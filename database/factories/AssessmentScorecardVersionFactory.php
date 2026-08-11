<?php

namespace Database\Factories;

use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScorecardVersion>
 */
class AssessmentScorecardVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_scorecard_id' => AssessmentScorecard::factory(),
            'version' => 1,
            'status' => 'draft',
            'change_notes' => 'Initial configuration.',
            'calculation_method' => 'mcda',
            'mcda_configuration' => ['normalization' => 'weighted_sum', 'missing_data' => 'incomplete'],
            'performance_thresholds' => [
                ['label' => 'Meets standard', 'minimum' => 70],
                ['label' => 'Needs improvement', 'minimum' => 0],
            ],
        ];
    }
}
