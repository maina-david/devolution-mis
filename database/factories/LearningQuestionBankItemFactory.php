<?php

namespace Database\Factories;

use App\Models\LearningQuestionBank;
use App\Models\LearningQuestionBankItem;
use App\Models\LearningQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningQuestionBankItem>
 */
class LearningQuestionBankItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_question_bank_id' => LearningQuestionBank::factory(),
            'learning_quiz_question_id' => LearningQuizQuestion::factory(),
            'variant_group' => fake()->bothify('objective-##'),
            'difficulty' => 'standard',
            'tags' => ['devolution'],
            'sequence' => 1,
        ];
    }
}
