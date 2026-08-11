<?php

namespace Database\Factories;

use App\Models\DataAsset;
use App\Models\PrivacyIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyIncident>
 */
class PrivacyIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_asset_id' => DataAsset::factory(),
            'reported_by' => User::factory(),
            'incident_lead_id' => User::factory(),
            'reference' => fake()->unique()->bothify('PBI-2026-######'),
            'title' => 'Unauthorized access to a controlled service record',
            'controller_role' => 'controller',
            'breach_type' => 'confidentiality',
            'description' => 'A controlled service record was accessed outside its approved role boundary.',
            'personal_data_categories' => ['identity', 'contact'],
            'estimated_data_subjects' => 1,
            'contains_sensitive_data' => false,
            'discovered_at' => now(),
            'regulator_notification_due_at' => now()->addHours(72),
        ];
    }
}
