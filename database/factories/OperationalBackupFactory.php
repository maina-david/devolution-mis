<?php

namespace Database\Factories;

use App\Models\OperationalBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalBackup>
 */
class OperationalBackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reference = fake()->unique()->bothify('BKP-########-######');

        return ['reference' => $reference, 'disk' => 'local', 'path' => "operations/backups/{$reference}.dump", 'database_name' => 'devolution_mis', 'format' => 'postgres_custom', 'sha256' => fake()->sha256(), 'size_bytes' => fake()->numberBetween(1_000_000, 50_000_000), 'status' => 'completed', 'started_at' => now()->subMinutes(2), 'completed_at' => now()];
    }
}
