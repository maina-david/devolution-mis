<?php

namespace Database\Factories;

use App\Models\ServiceLevelMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceLevelMeasurement>
 */
class ServiceLevelMeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['service' => 'idmis-web', 'metric' => 'readiness.database.latency', 'value' => fake()->randomFloat(2, 1, 100), 'unit' => 'milliseconds', 'target' => 1000, 'status' => 'pass', 'observed_at' => now()];
    }
}
