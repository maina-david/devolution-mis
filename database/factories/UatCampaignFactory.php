<?php

namespace Database\Factories;

use App\Models\ReferenceDataRelease;
use App\Models\UatCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UatCampaign>
 */
class UatCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_data_release_id' => ReferenceDataRelease::factory(),
            'created_by' => User::factory(),
            'code' => fake()->unique()->bothify('UAT-####-??'),
            'name' => 'Representative county pilot acceptance',
            'objective' => 'Validate representative end-to-end county journeys, accessibility and constrained-connectivity behavior before phased production rollout.',
            'environment' => 'government-hosting-uat',
            'starts_on' => now()->addMonth()->startOfDay(),
            'ends_on' => now()->addMonths(2)->startOfDay(),
            'status' => 'planning',
            'acceptance_criteria' => ['Every required county/scenario pair has a passing latest execution.', 'All findings are independently verified.', 'Required representative roles are covered.'],
            'required_roles' => ['county-official'],
            'minimum_counties' => 1,
        ];
    }
}
