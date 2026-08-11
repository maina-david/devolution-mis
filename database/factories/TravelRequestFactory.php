<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\TravelRequest;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRequest>
 */
class TravelRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TRV-'.now()->format('Y').'-'.fake()->unique()->bothify('????????'),
            'requester_id' => User::factory(),
            'county_id' => County::factory(),
            'travel_type' => 'domestic',
            'purpose' => fake()->sentence(5),
            'justification' => fake()->paragraph(),
            'destination_country' => ReferenceCatalogue::defaultCountryName(),
            'destination_county' => fake()->city(),
            'destination_city' => fake()->city(),
            'departure_date' => today()->addWeeks(2),
            'return_date' => today()->addWeeks(2)->addDays(2),
            'estimated_cost' => fake()->numberBetween(20000, 180000),
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'funding_source' => 'State Department operational budget',
            'cost_centre' => fake()->bothify('CC-####'),
            'hris_employee_reference' => fake()->bothify('IPPD-######'),
            'integration_status' => 'pending',
            'status' => 'draft',
            'priority' => 'normal',
            'created_by' => User::factory(),
        ];
    }
}
