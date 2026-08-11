<?php

namespace Database\Factories;

use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrResolutionDependency>
 */
class IgrResolutionDependencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dependent_resolution_id' => IgrResolution::factory(),
            'prerequisite_resolution_id' => IgrResolution::factory(),
            'dependency_type' => 'blocks',
            'rationale' => fake()->sentence(12),
            'created_by' => User::factory(),
        ];
    }
}
