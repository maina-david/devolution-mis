<?php

namespace Database\Factories;

use App\Models\PerformancePlan;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReview>
 */
class PerformanceReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_plan_id' => PerformancePlan::factory(), 'reviewer_id' => User::factory(), 'stage' => 'finalize_review', 'rating' => 80, 'comments' => fake()->paragraph(),
            'capacity_gaps' => fake()->sentence(), 'development_actions' => fake()->sentence(), 'reviewed_at' => now(),
        ];
    }
}
