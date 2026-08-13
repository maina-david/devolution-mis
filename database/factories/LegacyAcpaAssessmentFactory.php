<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use App\Models\LegacyAcpaAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyAcpaAssessment>
 */
class LegacyAcpaAssessmentFactory extends Factory
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
            'assessment_reference' => fake()->unique()->bothify('ACPA-####-??'),
            'period' => fake()->dateTimeBetween('-8 years', '-1 year')->format('Y-m-d'),
            'cycle_name' => fake()->year().' ACPA',
            'status' => 'historical_final',
            'overall_score' => fake()->randomFloat(2, 0, 100),
            'source_name' => 'Authorized historical ACPA register',
            'source_reference' => fake()->unique()->bothify('SOURCE-####'),
            'source_checksum' => fake()->sha256(),
            'record_checksum' => fake()->unique()->sha256(),
            'imported_by' => User::factory(),
            'imported_at' => now(),
        ];
    }
}
