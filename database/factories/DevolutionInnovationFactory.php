<?php

namespace Database\Factories;

use App\Models\DevolutionInnovation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevolutionInnovation>
 */
class DevolutionInnovationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['submitted_by' => User::factory(), 'reference' => fake()->unique()->bothify('INN-####-#####'), 'title' => fake()->sentence(5), 'problem_statement' => fake()->paragraph(), 'proposed_solution' => fake()->paragraph(), 'expected_impact' => fake()->paragraph(), 'maturity_level' => 'idea', 'stage' => 'concept', 'status' => 'draft'];
    }
}
