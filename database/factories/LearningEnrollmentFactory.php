<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningEnrollment>
 */
class LearningEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_course_id' => LearningCourse::factory(), 'user_id' => User::factory(), 'status' => 'enrolled', 'progress_percentage' => 0, 'enrolled_at' => now(), 'enrolled_by' => User::factory(),
        ];
    }
}
