<?php

namespace Database\Factories;

use App\Models\IgrForum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrForum>
 */
class IgrForumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('IGR-FORUM-###'), 'name' => fake()->sentence(4), 'forum_type' => 'council', 'mandate' => fake()->paragraph(), 'secretariat_user_id' => User::factory(), 'status' => 'active', 'created_by' => User::factory()];
    }
}
