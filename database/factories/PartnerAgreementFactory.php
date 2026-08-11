<?php

namespace Database\Factories;

use App\Models\PartnerAgreement;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerAgreement>
 */
class PartnerAgreementFactory extends Factory
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
            'reference' => 'MOU-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(5),
            'agreement_type' => 'mou',
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->addYears(3)->endOfYear(),
            'committed_value' => fake()->randomFloat(2, 1000000, 100000000),
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'summary' => fake()->paragraph(),
            'document_reference' => fake()->url(),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
