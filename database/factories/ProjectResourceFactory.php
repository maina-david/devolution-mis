<?php

namespace Database\Factories;

use App\Models\DevolutionProject;
use App\Models\ProjectResource;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectResource>
 */
class ProjectResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'devolution_project_id' => DevolutionProject::factory(),
            'code' => fake()->unique()->bothify('RES-###'),
            'name' => fake()->jobTitle(),
            'resource_type' => 'human',
            'capacity_unit' => 'hours',
            'capacity_per_day' => 8,
            'cost_rate' => 2500,
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'available_from' => today(),
            'available_to' => today()->addMonths(6),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
