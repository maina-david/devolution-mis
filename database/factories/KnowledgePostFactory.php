<?php

namespace Database\Factories;

use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgePost>
 */
class KnowledgePostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['knowledge_discussion_id' => KnowledgeDiscussion::factory(), 'author_id' => User::factory(), 'body' => fake()->paragraph(), 'is_moderated' => false, 'moderation_status' => 'visible', 'posted_at' => now()];
    }
}
