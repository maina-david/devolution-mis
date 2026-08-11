<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\Sector;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevolutionProject>
 */
class DevolutionProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRJ-####'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'sector_id' => Sector::factory(),
            'lead_county_id' => County::factory(),
            'lifecycle_stage' => 'initiation',
            'status' => 'draft',
            'planned_start_date' => today(),
            'planned_end_date' => today()->addYear(),
            'approved_budget' => fake()->randomFloat(2, 1000000, 100000000),
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'created_by' => User::factory(),
        ];
    }
}
