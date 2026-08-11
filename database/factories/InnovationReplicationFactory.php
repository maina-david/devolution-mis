<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\InnovationReplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InnovationReplication>
 */
class InnovationReplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceCounty = County::factory();

        return [
            'devolution_innovation_id' => DevolutionInnovation::factory()->state(['county_id' => $sourceCounty, 'status' => 'scaling', 'stage' => 'scale']),
            'source_county_id' => $sourceCounty,
            'target_county_id' => County::factory(),
            'accountable_user_id' => User::factory(),
            'created_by' => User::factory(),
            'reference' => fake()->unique()->bothify('REP-####-??????????'),
            'adaptation_plan' => 'Adapt the proven operating model to target-county governance, connectivity, language and service-delivery constraints.',
            'success_measure' => 'Percentage of target wards submitting complete records on time',
            'baseline_value' => 42,
            'target_value' => 85,
            'starts_on' => today(),
            'target_completion_on' => today()->addMonths(3),
            'status' => 'planned',
            'verification_decision' => 'pending',
        ];
    }
}
