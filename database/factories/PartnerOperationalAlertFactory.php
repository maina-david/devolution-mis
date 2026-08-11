<?php

namespace Database\Factories;

use App\Models\PartnerOperationalAlert;
use App\Models\PartnerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerOperationalAlert>
 */
class PartnerOperationalAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_profile_id' => PartnerProfile::factory(),
            'county_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'alert_type' => 'agreement_expiry_due',
            'severity' => 'warning',
            'fingerprint' => fake()->sha256(),
            'summary' => fake()->sentence(),
            'due_on' => today()->addDays(30),
            'status' => 'open',
            'detected_at' => now(),
        ];
    }
}
