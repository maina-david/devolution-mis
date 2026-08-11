<?php

namespace Database\Factories;

use App\Models\LearningLesson;
use App\Models\LearningModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningLesson>
 */
class LearningLessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_module_id' => LearningModule::factory(), 'title' => fake()->sentence(3), 'summary' => fake()->sentence(), 'content_type' => 'text', 'content_body' => fake()->paragraph(), 'content_checksum' => hash('sha256', fake()->sentence()), 'estimated_minutes' => 15, 'sequence' => 1, 'is_required' => true, 'is_downloadable' => false,
        ];
    }
}
