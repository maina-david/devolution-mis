<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\User;
use App\Models\VirtualClassroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualClassroom>
 */
class VirtualClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_course_id' => LearningCourse::factory(), 'facilitator_id' => User::factory(), 'title' => fake()->sentence(4), 'description' => fake()->sentence(), 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2), 'platform' => 'Microsoft Teams', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/test', 'capacity' => 100, 'status' => 'scheduled', 'created_by' => User::factory(),
        ];
    }
}
