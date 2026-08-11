<?php

namespace Database\Factories;

use App\Models\OperationalAlert;
use App\Models\OperationalAlertEvent;
use App\Models\ServiceLevelMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalAlertEvent>
 */
class OperationalAlertEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operational_alert_id' => OperationalAlert::factory(),
            'measurement_id' => ServiceLevelMeasurement::factory(),
            'actor_id' => null,
            'event_type' => 'opened',
            'status' => 'open',
            'narrative' => 'Queue oldest-job-age threshold exceeded.',
            'occurred_at' => now(),
            'evidence_checksum' => fake()->unique()->sha256(),
        ];
    }
}
