<?php

namespace Database\Factories;

use App\Models\SecurityThreat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityThreat>
 */
class SecurityThreatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'submitted_by' => User::factory(),
            'reference' => fake()->unique()->bothify('THR-2026-####'),
            'title' => fake()->sentence(5),
            'stride_category' => 'information_disclosure',
            'asset' => 'IDMIS personal-data repository',
            'scenario' => fake()->paragraph(),
            'threat_actor' => 'Compromised privileged account',
            'entry_points' => ['web_session', 'document_export'],
            'likelihood' => 4,
            'impact' => 5,
            'inherent_risk_score' => 20,
            'existing_controls' => ['rbac', 'mfa', 'audit'],
            'treatment_plan' => 'Enforce access certification, session revocation and export monitoring.',
            'treatment_status' => 'in_progress',
            'status' => 'submitted',
            'submitted_at' => now(),
            'review_due_at' => now()->addMonths(6),
        ];
    }
}
