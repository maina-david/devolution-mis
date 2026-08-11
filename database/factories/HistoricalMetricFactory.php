<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use App\Models\HistoricalMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricalMetric>
 */
class HistoricalMetricFactory extends Factory
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
            'data_migration_row_id' => DataMigrationRow::factory(),
            'county_id' => County::factory(),
            'dataset_type' => 'acpa_scores',
            'period' => fake()->dateTimeBetween('2018-01-01', '2025-12-31'),
            'metric_code' => 'ACPA-OVERALL',
            'metric_name' => 'Annual county performance assessment score',
            'numeric_value' => fake()->randomFloat(2, 0, 100),
            'narrative_value' => null,
            'unit' => 'percent',
            'source_name' => 'Authorized historical register',
            'source_reference' => 'ACPA-HISTORICAL-REGISTER',
            'source_checksum' => hash('sha256', fake()->uuid()),
            'record_checksum' => hash('sha256', fake()->uuid()),
            'imported_by' => User::factory(),
            'imported_at' => now(),
        ];
    }
}
