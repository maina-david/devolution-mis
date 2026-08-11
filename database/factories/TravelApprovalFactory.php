<?php

namespace Database\Factories;

use App\Models\TravelApproval;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelApproval>
 */
class TravelApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_request_id' => TravelRequest::factory(),
            'actor_id' => User::factory(),
            'stage' => 'manager',
            'decision' => 'approved',
            'rationale' => fake()->sentence(),
            'approved_cost' => fake()->numberBetween(20000, 180000),
            'source_system' => 'idmis',
            'decided_at' => now(),
        ];
    }
}
