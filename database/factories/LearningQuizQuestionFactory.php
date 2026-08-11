<?php

namespace Database\Factories;

use App\Models\LearningLesson;
use App\Models\LearningQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningQuizQuestion>
 */
class LearningQuizQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_lesson_id' => LearningLesson::factory(), 'question' => fake()->sentence().'?', 'options' => ['A' => 'Correct answer', 'B' => 'Incorrect answer'], 'correct_option' => 'A', 'explanation' => fake()->sentence(), 'points' => 1, 'sequence' => 1,
        ];
    }
}
