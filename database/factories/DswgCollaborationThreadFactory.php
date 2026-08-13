<?php

namespace Database\Factories;

use App\Models\DswgCollaborationThread;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgCollaborationThread>
 */
class DswgCollaborationThreadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dswg_working_group_id' => DswgWorkingGroup::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(6),
            'topic' => fake()->paragraph(),
            'status' => 'open',
            'last_activity_at' => now(),
        ];
    }
}
