<?php

namespace Database\Factories;

use App\Models\IgrForum;
use App\Models\IgrForumMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrForumMeeting>
 */
class IgrForumMeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'igr_forum_id' => IgrForum::factory(),
            'reference' => fake()->unique()->bothify('IGR-MTG-####-###'),
            'title' => fake()->sentence(5),
            'held_on' => today()->subWeek(),
            'venue' => fake()->city(),
            'chair_user_id' => User::factory(),
            'quorum_confirmed' => true,
            'minutes_reference' => fake()->bothify('MIN/####/###'),
            'created_by' => User::factory(),
        ];
    }
}
