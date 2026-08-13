<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\LearningQuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningQuestionBank>
 */
class LearningQuestionBankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_course_id' => LearningCourse::factory(),
            'code' => fake()->unique()->bothify('BANK-###'),
            'title' => fake()->sentence(4),
            'selection_count' => 1,
            'randomize_questions' => true,
            'randomize_options' => true,
            'version' => 1,
            'status' => 'draft',
            'checksum' => hash('sha256', fake()->uuid()),
            'created_by' => User::factory(),
            'published_at' => now(),
        ];
    }
}
