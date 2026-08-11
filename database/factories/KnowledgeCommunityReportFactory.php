<?php

namespace Database\Factories;

use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeCommunityReport>
 */
class KnowledgeCommunityReportFactory extends Factory
{
    protected $model = KnowledgeCommunityReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['knowledge_post_id' => KnowledgePost::factory(), 'reported_by' => User::factory(), 'reference' => fake()->unique()->bothify('KMR-####-????'), 'category' => 'misinformation', 'severity' => 'medium', 'description' => fake()->paragraph(), 'status' => 'reported'];
    }
}
