<?php

namespace Database\Factories;

use App\Models\CountyGrant;
use App\Models\ExchequerRequest;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchequerRequest>
 */
class ExchequerRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['county_grant_id' => CountyGrant::factory(), 'county_id' => fn (array $attributes) => CountyGrant::query()->whereKey((string) $attributes['county_grant_id'])->firstOrFail()->county_id, 'created_by' => User::factory(), 'request_reference' => fake()->unique()->bothify('EXQ-2026-########'), 'tranche_reference' => fake()->unique()->bothify('TRANCHE-###'), 'financial_year' => '2025/26', 'amount' => 10_000_000, 'currency' => ReferenceCatalogue::defaultCurrency(), 'current_stage' => 'prepared', 'status' => 'open'];
    }
}
