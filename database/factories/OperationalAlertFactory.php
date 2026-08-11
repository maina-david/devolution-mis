<?php

namespace Database\Factories;

use App\Models\OperationalAlert;
use App\Models\ServiceLevelMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalAlert>
 */
class OperationalAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'initial_measurement_id' => ServiceLevelMeasurement::factory(),
            'latest_measurement_id' => fn (array $attributes): string => $attributes['initial_measurement_id'],
            'service' => 'queue',
            'metric' => 'oldest_job_age',
            'severity' => 'warning',
            'status' => 'open',
            'latest_value' => 420,
            'threshold' => 300,
            'unit' => 'seconds',
            'occurrence_count' => 1,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
            'evidence_checksum' => fake()->unique()->sha256(),
        ];
    }
}
