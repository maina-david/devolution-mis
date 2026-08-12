<?php

namespace Database\Factories;

use App\Models\UatCampaign;
use App\Models\UatScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UatScenario>
 */
class UatScenarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uat_campaign_id' => UatCampaign::factory(),
            'created_by' => User::factory(),
            'code' => fake()->unique()->bothify('UAT-SCN-####'),
            'module' => 'devolution-assessment',
            'title' => 'Submit a county assessment evidence package',
            'actor_role' => 'county-official',
            'priority' => 'critical',
            'journey' => 'The authorized county official completes and submits a governed assessment evidence package.',
            'preconditions' => ['A published assessment cycle and scorecard are available.', 'The representative user has county-scoped access.'],
            'steps' => ['Open the assigned assessment.', 'Upload clean evidence and complete attestation.', 'Submit the package for independent verification.'],
            'expected_result' => 'The submission is accepted once, retains evidence lineage and becomes visible only to authorized reviewers.',
            'accessibility_needs' => 'Complete the journey with keyboard-only navigation and announced validation errors.',
            'low_connectivity_variant' => 'Repeat with constrained bandwidth and verify recoverable upload behavior.',
            'required' => true,
            'status' => 'ready',
        ];
    }
}
