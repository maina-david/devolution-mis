<?php

namespace Database\Factories;

use App\Models\AccessDelegation;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessDelegation>
 */
class AccessDelegationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'beneficiary_id' => User::factory()->withTwoFactor(),
            'reference' => fake()->unique()->bothify('DAG-2026-######'),
            'access_type' => 'delegated',
            'scope_type' => 'county_portfolio',
            'permission_scope' => ['projects:view'],
            'county_scope_snapshot' => [County::factory()->create()->identityCell()],
            'business_justification' => 'Temporary coverage is required during an approved officer absence.',
            'starts_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }
}
