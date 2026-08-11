<?php

namespace Database\Factories;

use App\Models\IntegrationExchange;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionSourceMatch;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerContributionSourceMatch>
 */
class PartnerContributionSourceMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reconciliation_run_id' => ReconciliationRun::factory(),
            'integration_exchange_id' => IntegrationExchange::factory(),
            'partner_contribution_id' => PartnerContribution::factory(),
            'matched_by' => User::factory(),
            'matched_by_name' => fake()->name(),
            'external_reference' => fake()->unique()->bothify('EXT-########'),
            'outcome' => 'matched',
            'source_committed_amount' => 10000000,
            'source_disbursed_amount' => 4000000,
            'source_in_kind_value' => 0,
            'local_committed_amount' => 10000000,
            'local_disbursed_amount' => 4000000,
            'local_in_kind_value' => 0,
            'disbursement_variance' => 0,
            'source_currency' => ReferenceCatalogue::defaultCurrency(),
            'local_currency' => ReferenceCatalogue::defaultCurrency(),
            'source_checksum' => fake()->sha256(),
            'match_checksum' => fake()->unique()->sha256(),
            'snapshot' => ['outcome' => 'matched'],
            'matched_at' => now(),
        ];
    }
}
