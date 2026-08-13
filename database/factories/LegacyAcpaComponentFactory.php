<?php

namespace Database\Factories;

use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use App\Models\LegacyAcpaAssessment;
use App\Models\LegacyAcpaComponent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyAcpaComponent>
 */
class LegacyAcpaComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_acpa_assessment_id' => LegacyAcpaAssessment::factory(),
            'data_migration_batch_id' => DataMigrationBatch::factory(),
            'data_migration_row_id' => DataMigrationRow::factory(),
            'record_type' => 'criterion_result',
            'record_reference' => fake()->unique()->bothify('CRIT-###'),
            'criterion_code' => fake()->bothify('KRA-##'),
            'title' => fake()->sentence(4),
            'numeric_value' => fake()->randomFloat(2, 0, 10),
            'maximum_value' => 10,
            'status' => 'verified',
            'source_reference' => fake()->unique()->bothify('SOURCE-####'),
            'source_payload' => ['provenance' => 'factory'],
            'source_checksum' => fake()->sha256(),
            'record_checksum' => fake()->unique()->sha256(),
            'imported_by' => User::factory(),
            'imported_at' => now(),
        ];
    }
}
