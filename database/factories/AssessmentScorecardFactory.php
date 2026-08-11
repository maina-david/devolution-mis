<?php

namespace Database\Factories;

use App\Models\AssessmentScorecard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScorecard>
 */
class AssessmentScorecardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SC-####'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
