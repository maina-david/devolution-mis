<?php

namespace Database\Factories;

use App\Models\DswgMeeting;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgMeeting>
 */
class DswgMeetingFactory extends Factory
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
            'reference' => fake()->unique()->bothify('DSWG-MTG-####'),
            'title' => fake()->sentence(5),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
            'meeting_mode' => 'hybrid',
            'venue' => 'State Department conference room',
            'virtual_link' => 'https://meet.example.org/dswg',
            'agenda' => fake()->paragraph(),
            'quorum_required' => 2,
            'status' => 'scheduled',
            'organized_by' => User::factory(),
        ];
    }
}
