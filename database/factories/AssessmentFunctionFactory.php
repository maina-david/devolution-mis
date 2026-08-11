<?php

namespace Database\Factories;

use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecardVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentFunction>
 */
class AssessmentFunctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_scorecard_version_id' => AssessmentScorecardVersion::factory(),
            'code' => fake()->unique()->bothify('F-##'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'function_type' => 'devolved',
            'weight' => 100,
            'sequence' => 1,
        ];
    }
}
