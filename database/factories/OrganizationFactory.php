<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('ORG-####'),
            'name' => fake()->unique()->company(),
            'type' => fake()->randomElement(['national', 'county', 'development_partner', 'civil_society']),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
