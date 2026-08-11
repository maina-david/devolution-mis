<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerProfile>
 */
class PartnerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->state(['type' => 'development_partner']),
            'partner_type' => fake()->randomElement(['bilateral', 'multilateral', 'foundation', 'ngo']),
            'country' => fake()->country(),
            'website' => fake()->url(),
            'focal_point_name' => fake()->name(),
            'focal_point_email' => fake()->unique()->safeEmail(),
            'focal_point_phone' => fake()->e164PhoneNumber(),
            'strategic_priorities' => fake()->sentence(),
            'modalities' => ['grants', 'technical_assistance'],
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
