<?php

namespace Database\Factories;

use App\Models\DataMigrationBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataMigrationBatch>
 */
class DataMigrationBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'MIG-'.fake()->unique()->numerify('########'),
            'dataset_type' => fake()->randomElement(['acpa_scores', 'performance_metrics', 'evaluation_baselines']),
            'source_name' => 'Authorized historical register',
            'source_reference' => 'SOURCE-'.fake()->unique()->numerify('#####'),
            'period_from' => '2018-01-01',
            'period_to' => '2025-12-31',
            'original_name' => 'historical-metrics.csv',
            'mime_type' => 'text/csv',
            'size_bytes' => 1024,
            'path' => 'data-migrations/'.fake()->uuid().'.csv',
            'file_checksum' => hash('sha256', fake()->uuid()),
            'status' => 'validated',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'validation_report' => ['error_counts' => []],
            'submitted_by' => User::factory(),
        ];
    }
}
