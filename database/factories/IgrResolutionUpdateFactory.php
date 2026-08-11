<?php

namespace Database\Factories;

use App\Models\IgrResolution;
use App\Models\IgrResolutionUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrResolutionUpdate>
 */
class IgrResolutionUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['igr_resolution_id' => IgrResolution::factory(), 'progress_percentage' => 40, 'narrative' => fake()->paragraph(), 'implementation_gap' => fake()->sentence(), 'reported_by' => User::factory(), 'reported_at' => now()];
    }
}
