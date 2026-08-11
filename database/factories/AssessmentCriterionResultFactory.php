<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCriterionResult>
 */
class AssessmentCriterionResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'assessment_criterion_id' => AssessmentCriterion::factory(),
            'submitted_score' => 70,
        ];
    }
}
