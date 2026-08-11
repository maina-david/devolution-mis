<?php

namespace Database\Factories;

use App\Models\LearningCohort;
use App\Models\LearningCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCohort>
 */
class LearningCohortFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['learning_course_id' => LearningCourse::factory(), 'instructor_id' => User::factory(), 'created_by' => User::factory(), 'code' => fake()->unique()->bothify('LC-####-????'), 'name' => fake()->sentence(4), 'description' => fake()->paragraph(), 'capacity' => 30, 'enrollment_opens_on' => now()->addDays(2)->toDateString(), 'enrollment_closes_on' => now()->addDays(9)->toDateString(), 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(40), 'status' => 'draft'];
    }
}
