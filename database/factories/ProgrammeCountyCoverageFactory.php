<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeCountyCoverage>
 */
class ProgrammeCountyCoverageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'county_id' => County::factory(),
            'implementation_lead_id' => Organization::factory(),
            'created_by' => User::factory()->devolutionAdmin(),
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
            'funding_allocation' => 25000000,
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'source_reference' => 'SDD/PROGRAMME-COVERAGE/2026/001',
            'notes' => 'Approved county implementation coverage.',
        ];
    }
}
