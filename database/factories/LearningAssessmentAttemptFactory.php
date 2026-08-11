<?php

namespace Database\Factories;

use App\Models\LearningAssessmentAttempt;
use App\Models\LearningEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningAssessmentAttempt>
 */
class LearningAssessmentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_enrollment_id' => LearningEnrollment::factory(), 'attempt_number' => 1, 'answers' => [], 'result_snapshot' => [], 'score' => 80, 'passed' => true, 'submitted_at' => now(),
        ];
    }
}
