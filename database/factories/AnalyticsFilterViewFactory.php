<?php

namespace Database\Factories;

use App\Models\AnalyticsFilterView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsFilterView>
 */
class AnalyticsFilterViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'filters' => ['status' => 'published'],
            'is_default' => false,
        ];
    }
}
