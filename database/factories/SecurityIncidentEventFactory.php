<?php

namespace Database\Factories;

use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SecurityIncidentEvent>
 */
class SecurityIncidentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'security_incident_id' => SecurityIncident::factory(),
            'actor_name' => 'system:test',
            'transition' => 'detect',
            'from_status' => 'none',
            'to_status' => 'detected',
            'narrative' => fake()->sentence(12),
            'occurred_at' => now(),
            'evidence_checksum' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
