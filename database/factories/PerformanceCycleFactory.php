<?php

namespace Database\Factories;

use App\Models\PerformanceCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCycle>
 */
class PerformanceCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PERF-'.fake()->unique()->bothify('####-??'), 'name' => fake()->year().' Performance Cycle', 'period_start' => today()->startOfYear(), 'period_end' => today()->endOfYear(),
            'goal_setting_deadline' => today()->startOfYear()->addMonth(), 'midterm_review_deadline' => today()->startOfYear()->addMonths(6), 'final_review_deadline' => today()->endOfYear(), 'status' => 'open', 'created_by' => User::factory(),
        ];
    }
}
