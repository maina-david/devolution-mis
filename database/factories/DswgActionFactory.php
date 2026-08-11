<?php

namespace Database\Factories;

use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgAction>
 */
class DswgActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dswg_meeting_id' => DswgMeeting::factory(),
            'code' => fake()->unique()->bothify('DSWG-ACT-####'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'accountable_user_id' => User::factory(),
            'accountable_organization_id' => Organization::factory(),
            'due_on' => today()->addMonth(),
            'priority' => 'medium',
            'status' => 'open',
            'progress_percentage' => 0,
            'created_by' => User::factory(),
        ];
    }
}
