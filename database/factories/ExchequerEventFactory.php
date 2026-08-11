<?php

namespace Database\Factories;

use App\Models\ExchequerEvent;
use App\Models\ExchequerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchequerEvent>
 */
class ExchequerEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['exchequer_request_id' => ExchequerRequest::factory(), 'recorded_by' => User::factory(), 'source_system' => 'TREASURY', 'event_type' => 'submitted_to_treasury', 'source_event_reference' => fake()->unique()->uuid(), 'occurred_at' => now(), 'received_at' => now(), 'elapsed_from_previous_minutes' => 0, 'elapsed_total_minutes' => 0, 'evidence_checksum' => fake()->sha256()];
    }
}
