<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataMigrationRow>
 */
class DataMigrationRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_migration_batch_id' => DataMigrationBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 5000),
            'county_id' => County::factory(),
            'period' => fake()->dateTimeBetween('2018-01-01', '2025-12-31'),
            'metric_code' => 'ACPA-OVERALL',
            'metric_name' => 'Annual county performance assessment score',
            'numeric_value' => fake()->randomFloat(2, 0, 100),
            'narrative_value' => null,
            'unit' => 'percent',
            'source_reference' => 'ACPA-HISTORICAL-REGISTER',
            'source_payload' => ['county_code' => '001', 'metric_code' => 'ACPA-OVERALL'],
            'source_checksum' => hash('sha256', fake()->uuid()),
            'validation_status' => 'valid',
            'validation_errors' => [],
        ];
    }
}
