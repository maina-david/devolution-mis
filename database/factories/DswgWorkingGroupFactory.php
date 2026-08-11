<?php

namespace Database\Factories;

use App\Models\DswgWorkingGroup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgWorkingGroup>
 */
class DswgWorkingGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DSWG-###'),
            'name' => fake()->sentence(4).' working group',
            'mandate' => fake()->paragraph(),
            'scope' => fake()->randomElement(['national', 'regional', 'sector']),
            'lead_organization_id' => Organization::factory(),
            'secretariat_user_id' => User::factory(),
            'meeting_frequency' => 'Quarterly',
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
