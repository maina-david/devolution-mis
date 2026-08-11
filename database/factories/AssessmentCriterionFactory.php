<?php

namespace Database\Factories;

use App\Models\AssessmentCriterion;
use App\Models\AssessmentStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCriterion>
 */
class AssessmentCriterionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_standard_id' => AssessmentStandard::factory(),
            'code' => fake()->unique()->bothify('C-##'),
            'name' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'weight' => 100,
            'maximum_score' => 100,
            'scoring_method' => 'scale',
            'formula' => ['type' => 'linear'],
            'thresholds' => [['label' => 'Compliant', 'minimum' => 70]],
            'is_mandatory' => true,
            'sequence' => 1,
        ];
    }
}
