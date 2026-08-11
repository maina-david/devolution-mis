<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsDashboard>
 */
class AnalyticsDashboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'code' => 'ANL-'.fake()->unique()->numerify('#####'),
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'audience_roles' => ['devolution-admin'],
            'status' => 'draft',
        ];
    }
}
