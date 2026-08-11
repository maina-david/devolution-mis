<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Programme;
use App\Models\Sector;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRG-####'),
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'lead_organization_id' => Organization::factory(),
            'sector_id' => Sector::factory(),
            'starts_on' => today(),
            'ends_on' => today()->addYear(),
            'status' => 'active',
            'budget_amount' => fake()->randomFloat(2, 1000000, 100000000),
            'currency' => ReferenceCatalogue::defaultCurrency(),
        ];
    }
}
