<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LearningCourse>
 */
class LearningCourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slugSource = fake()->unique()->words(4, true);

        return [
            'owner_id' => User::factory(), 'code' => fake()->unique()->bothify('LRN-###-??'), 'slug' => Str::slug(is_string($slugSource) ? $slugSource : implode(' ', $slugSource)), 'title' => fake()->sentence(4), 'summary' => fake()->sentence(), 'description' => fake()->paragraph(), 'category' => 'Devolution foundations', 'level' => 'foundation', 'delivery_mode' => 'self_paced', 'language' => ReferenceCatalogue::defaultLanguage(), 'estimated_minutes' => 60, 'passing_score' => 70, 'maximum_attempts' => 3, 'status' => 'draft', 'created_by' => User::factory(),
        ];
    }
}
