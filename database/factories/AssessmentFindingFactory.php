<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentFinding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentFinding>
 */
class AssessmentFindingFactory extends Factory
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
            'code' => fake()->unique()->bothify('FIND-###'),
            'severity' => 'minor',
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'raised_by' => User::factory(),
        ];
    }
}
