<?php

namespace Database\Factories;

use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDiscussionSubscription>
 */
class KnowledgeDiscussionSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['knowledge_discussion_id' => KnowledgeDiscussion::factory(), 'user_id' => User::factory(), 'delivery_frequency' => 'instant', 'subscribed_at' => now()];
    }
}
