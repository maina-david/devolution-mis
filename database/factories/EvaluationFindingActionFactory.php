<?php

namespace Database\Factories;

use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationFindingAction>
 */
class EvaluationFindingActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['evaluation_finding_id' => EvaluationFinding::factory(), 'accountable_owner_id' => User::factory(), 'created_by' => User::factory(), 'code' => fake()->unique()->bothify('ACT-###'), 'title' => fake()->sentence(), 'description' => fake()->paragraph(), 'success_indicator' => fake()->sentence(), 'target' => '100 percent', 'due_at' => now()->addMonth(), 'weight_percentage' => 100, 'checksum' => fake()->sha256()];
    }
}
