<?php

namespace Database\Factories;

use App\Models\IgrGapCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrGapCategory>
 */
class IgrGapCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('IGR-GAP-###'),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(12),
            'default_severity' => 'medium',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
