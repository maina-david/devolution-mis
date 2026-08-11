<?php

namespace Database\Factories;

use App\Models\KnowledgeDiscussion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDiscussion>
 */
class KnowledgeDiscussionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['created_by' => User::factory(), 'title' => fake()->sentence(6), 'prompt' => fake()->paragraph(), 'status' => 'open', 'visibility' => 'national', 'last_posted_at' => now()];
    }
}
