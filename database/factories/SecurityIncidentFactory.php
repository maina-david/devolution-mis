<?php

namespace Database\Factories;

use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityIncident>
 */
class SecurityIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reported_by' => User::factory()->devolutionAdmin(),
            'incident_lead_id' => User::factory()->platformAdmin(),
            'reference' => 'SEC-LIVE-'.fake()->unique()->numerify('########'),
            'record_type' => 'live',
            'playbook' => 'credential_compromise',
            'title' => fake()->sentence(6),
            'summary' => fake()->paragraph(),
            'affected_services' => ['identity', 'application'],
            'data_exposure' => 'suspected',
            'severity' => 'sev2',
            'status' => 'detected',
            'business_impact' => fake()->sentence(12),
            'detected_at' => now(),
            'acknowledgement_due_at' => now()->addMinutes(30),
            'containment_due_at' => now()->addHours(4),
            'last_transition_at' => now(),
        ];
    }
}
