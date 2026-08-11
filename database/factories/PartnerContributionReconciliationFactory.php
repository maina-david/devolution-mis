<?php

namespace Database\Factories;

use App\Models\PartnerContribution;
use App\Models\PartnerContributionReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerContributionReconciliation>
 */
class PartnerContributionReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_contribution_id' => PartnerContribution::factory(),
            'version' => 1,
            'decision' => 'verified',
            'verified_committed_amount' => '1000000.00',
            'verified_disbursed_amount' => '750000.00',
            'verified_in_kind_value' => '0.00',
            'disbursement_variance' => '0.00',
            'source_reference' => fake()->bothify('BANK-####-????'),
            'review_note' => fake()->sentence(12),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'evidence_checksum' => hash('sha256', fake()->uuid()),
            'predecessor_checksum' => null,
            'decision_checksum' => hash('sha256', fake()->uuid()),
            'snapshot' => ['decision' => 'verified'],
        ];
    }
}
