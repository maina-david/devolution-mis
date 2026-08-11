<?php

namespace Database\Factories;

use App\Models\PartnerCollaborationAlert;
use App\Models\PartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCollaborationAlert>
 */
class PartnerCollaborationAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'primary_partner_id' => PartnerProfile::factory(),
            'related_partner_id' => PartnerProfile::factory(),
            'alert_type' => 'synergy',
            'severity' => 'medium',
            'scope_fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'scope' => ['county_ids' => [], 'sector_ids' => [], 'project_ids' => []],
            'summary' => fake()->sentence(),
            'status' => 'open',
            'detected_at' => now(),
        ];
    }
}
