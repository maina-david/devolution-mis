<?php

namespace Database\Factories;

use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectivePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCorrectiveAction>
 */
class AssessmentCorrectiveActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['assessment_corrective_plan_id' => AssessmentCorrectivePlan::factory(), 'accountable_owner_id' => User::factory(), 'code' => fake()->unique()->bothify('ACT-###'), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'success_indicator' => fake()->sentence(), 'target' => fake()->sentence(), 'due_at' => now()->addWeeks(3), 'progress_percentage' => 0, 'status' => 'planned'];
    }
}
