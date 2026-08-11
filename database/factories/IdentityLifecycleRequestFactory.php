<?php

namespace Database\Factories;

use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdentityLifecycleRequest>
 */
class IdentityLifecycleRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_system' => 'IPPD-HRIS',
            'source_event_id' => fake()->unique()->bothify('HR-JML-####'),
            'source_evidence_reference' => fake()->bothify('DMS-HR-JML-####'),
            'source_checksum' => hash('sha256', fake()->uuid()),
            'event_type' => 'leaver',
            'user_id' => User::factory(),
            'effective_at' => now()->subDay(),
            'current_access_snapshot' => ['role' => 'county-official', 'home_county_id' => null, 'assigned_county_ids' => [], 'delegated_access_ids' => [], 'access_revoked_at' => null],
            'proposed_role' => null,
            'proposed_home_county_id' => null,
            'proposed_assigned_county_ids' => [],
            'business_reason' => 'The source workforce event requires a controlled reconciliation of the identity access state.',
            'status' => 'pending',
            'requested_by' => User::factory(),
        ];
    }
}
