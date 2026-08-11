<?php

namespace Database\Factories;

use App\Models\Programme;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeEvaluation>
 */
class ProgrammeEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'code' => fake()->unique()->bothify('EVAL-####'),
            'title' => fake()->sentence(5),
            'evaluation_type' => fake()->randomElement(['baseline', 'midline', 'endline']),
            'period_start' => now()->subYear()->toDateString(),
            'period_end' => now()->toDateString(),
            'status' => 'planned',
            'terms_of_reference' => fake()->paragraphs(2, true),
            'lead_evaluator_id' => User::factory(),
        ];
    }
}
