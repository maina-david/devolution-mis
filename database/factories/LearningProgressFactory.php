<?php

namespace Database\Factories;

use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningProgress>
 */
class LearningProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_enrollment_id' => LearningEnrollment::factory(), 'learning_lesson_id' => LearningLesson::factory(), 'status' => 'completed', 'progress_percentage' => 100, 'time_spent_seconds' => 900, 'started_at' => now()->subMinutes(15), 'completed_at' => now(), 'last_position_at' => now(),
        ];
    }
}
