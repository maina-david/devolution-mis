<?php

namespace Database\Factories;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoalVersion>
 */
class PerformanceGoalVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_goal_id' => PerformanceGoal::factory(),
            'version' => 1,
            'definition_snapshot' => [
                'code' => fake()->unique()->bothify('KPI-###'),
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'kpi' => fake()->sentence(3),
                'unit_of_measure' => 'percent',
                'baseline_value' => '50.0000',
                'target_value' => '90.0000',
                'weight' => '100.00',
            ],
            'predecessor_checksum' => null,
            'version_checksum' => hash('sha256', fake()->uuid()),
            'created_by' => User::factory(),
            'effective_at' => now(),
        ];
    }
}
