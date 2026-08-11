<?php

namespace Database\Factories;

use App\Models\IntegrationSystem;
use App\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationRun>
 */
class ReconciliationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['integration_system_id' => IntegrationSystem::factory(), 'reference' => fake()->unique()->bothify('REC-####-#####'), 'period_from' => today()->startOfMonth(), 'period_to' => today(), 'source_count' => 10, 'target_count' => 10, 'matched_count' => 10, 'exception_count' => 0, 'status' => 'reconciled', 'result_checksum' => fake()->sha256(), 'started_at' => now()->subMinute(), 'completed_at' => now()];
    }
}
