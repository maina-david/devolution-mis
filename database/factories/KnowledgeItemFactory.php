<?php

namespace Database\Factories;

use App\Models\KnowledgeItem;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeItem>
 */
class KnowledgeItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['author_id' => User::factory(), 'reference' => fake()->unique()->bothify('KM-####-#####'), 'item_type' => fake()->randomElement(['best_practice', 'case_study', 'research', 'publication', 'toolkit', 'blog']), 'title' => fake()->sentence(6), 'summary' => fake()->paragraph(), 'content_body' => fake()->paragraphs(3, true), 'tags' => fake()->randomElements(['planning', 'public finance', 'citizen participation', 'service delivery'], 2), 'visibility' => 'national', 'status' => 'draft', 'source_organization' => fake()->company(), 'language' => ReferenceCatalogue::defaultLanguage()];
    }
}
