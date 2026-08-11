<?php

namespace Database\Factories;

use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalAmendmentDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoalAmendmentDecision>
 */
class PerformanceGoalAmendmentDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_goal_amendment_id' => PerformanceGoalAmendment::factory(),
            'decision' => 'rejected',
            'rationale' => fake()->sentence(12),
            'decided_by' => User::factory(),
            'decided_at' => now(),
            'applied_version_id' => null,
            'decision_checksum' => hash('sha256', fake()->uuid()),
            'decision_snapshot' => ['decision' => 'rejected'],
        ];
    }
}
