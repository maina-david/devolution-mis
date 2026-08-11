<?php

namespace Database\Factories;

use App\Models\ServiceDeskPolicy;
use App\Models\ServiceDeskRosterMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceDeskRosterMember>
 */
class ServiceDeskRosterMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_desk_policy_id' => ServiceDeskPolicy::factory(),
            'user_id' => User::factory(),
            'county_id' => null,
            'tier' => 1,
            'duty_role' => 'responder',
            'is_primary' => true,
            'starts_at' => now()->startOfDay(),
            'ends_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
