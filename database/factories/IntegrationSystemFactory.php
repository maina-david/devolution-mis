<?php

namespace Database\Factories;

use App\Models\IntegrationSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationSystem>
 */
class IntegrationSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('SYS-###-??'), 'name' => fake()->company().' interface', 'purpose' => fake()->paragraph(), 'system_owner' => fake()->company(), 'environment' => 'sandbox', 'transport' => 'fixture', 'auth_scheme' => 'none', 'direction' => 'bidirectional', 'data_classification' => 'official', 'status' => 'contract_review', 'health_status' => 'unknown'];
    }
}
