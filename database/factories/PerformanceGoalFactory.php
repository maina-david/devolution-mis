<?php

namespace Database\Factories;

use App\Models\PerformanceGoal;
use App\Models\PerformancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoal>
 */
class PerformanceGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_plan_id' => PerformancePlan::factory(), 'code' => fake()->unique()->bothify('KPI-###'), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'kpi' => fake()->sentence(3),
            'unit_of_measure' => 'percent', 'baseline_value' => 50, 'target_value' => 90, 'weight' => 100, 'sequence' => 1,
        ];
    }
}
