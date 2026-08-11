<?php

namespace Database\Factories;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalVersion;
use App\Models\PerformancePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoalAmendment>
 */
class PerformanceGoalAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_plan_id' => PerformancePlan::factory(),
            'performance_goal_id' => PerformanceGoal::factory(),
            'base_version_id' => PerformanceGoalVersion::factory(),
            'request_version' => 1,
            'proposed_snapshot' => [
                'code' => fake()->unique()->bothify('KPI-###'),
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'kpi' => fake()->sentence(3),
                'unit_of_measure' => 'percent',
                'baseline_value' => '50.0000',
                'target_value' => '95.0000',
                'weight' => '100.00',
            ],
            'reason' => fake()->sentence(12),
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'predecessor_checksum' => null,
            'request_checksum' => hash('sha256', fake()->uuid()),
        ];
    }
}
