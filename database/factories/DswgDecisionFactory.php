<?php

namespace Database\Factories;

use App\Models\DswgDecision;
use App\Models\DswgMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgDecision>
 */
class DswgDecisionFactory extends Factory
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
            'code' => fake()->unique()->bothify('DSWG-DEC-####'),
            'title' => fake()->sentence(5),
            'decision_text' => fake()->paragraph(),
            'decision_type' => 'resolution',
            'status' => 'adopted',
            'decided_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
