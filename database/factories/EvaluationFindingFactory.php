<?php

namespace Database\Factories;

use App\Models\EvaluationFinding;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationFinding>
 */
class EvaluationFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['programme_evaluation_id' => ProgrammeEvaluation::factory(), 'accountable_owner_id' => User::factory(), 'created_by' => User::factory(), 'reference' => fake()->unique()->bothify('EVAL-F-####'), 'title' => fake()->sentence(5), 'finding' => fake()->paragraph(), 'recommendation' => fake()->paragraph(), 'severity' => fake()->randomElement(['low', 'moderate', 'high', 'critical']), 'due_at' => now()->addMonth()->toDateString(), 'checksum' => fake()->sha256()];
    }
}
