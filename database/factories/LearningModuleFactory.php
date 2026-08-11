<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\LearningModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningModule>
 */
class LearningModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_course_id' => LearningCourse::factory(), 'title' => fake()->sentence(3), 'description' => fake()->sentence(), 'sequence' => 1, 'is_required' => true,
        ];
    }
}
