<?php

namespace Database\Factories;

use App\Models\ReferenceDataRelease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceDataRelease>
 */
class ReferenceDataReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = ['counties' => [], 'organizations' => [], 'sectors' => [], 'programmes' => []];

        return ['version' => fake()->unique()->numberBetween(1, 100000), 'submitted_by' => User::factory(), 'status' => 'submitted', 'change_summary' => fake()->sentence(), 'snapshot' => $snapshot, 'checksum' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'submitted_at' => now()];
    }
}
