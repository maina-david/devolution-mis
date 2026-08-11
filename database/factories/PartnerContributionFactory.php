<?php

namespace Database\Factories;

use App\Models\DevolutionProject;
use App\Models\PartnerContribution;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerContribution>
 */
class PartnerContributionFactory extends Factory
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
            'devolution_project_id' => DevolutionProject::factory(),
            'financial_year' => '2026/2027',
            'contribution_type' => 'grant',
            'committed_amount' => 10000000,
            'disbursed_amount' => 4000000,
            'in_kind_value' => 0,
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'description' => fake()->sentence(),
            'status' => 'disbursing',
            'reported_by' => User::factory(),
            'provenance' => ['source_system' => 'Partner portal', 'captured_at' => now()->toIso8601String()],
        ];
    }
}
