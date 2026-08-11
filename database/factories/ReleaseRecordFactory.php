<?php

namespace Database\Factories;

use App\Models\ReleaseRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseRecord>
 */
class ReleaseRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['version' => fake()->unique()->numerify('2026.##.##'), 'git_sha' => sha1(fake()->uuid()), 'environment' => 'pilot', 'artifact_checksum' => fake()->sha256(), 'change_reference' => fake()->unique()->bothify('CHG-IDMIS-####-###'), 'migration_batch' => fake()->numberBetween(1, 100), 'status' => 'deployed', 'deployed_at' => now(), 'notes' => fake()->sentence()];
    }
}
