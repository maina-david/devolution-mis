<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFolder>
 */
class DocumentFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'county_id' => County::factory(),
            'name' => fake()->unique()->words(3, true),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function national(): static
    {
        return $this->state(fn (): array => ['county_id' => null]);
    }

    public function within(DocumentFolder $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id, 'county_id' => $parent->county_id]);
    }
}
