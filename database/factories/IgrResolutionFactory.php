<?php

namespace Database\Factories;

use App\Models\IgrForum;
use App\Models\IgrResolution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrResolution>
 */
class IgrResolutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['igr_forum_id' => IgrForum::factory(), 'resolution_number' => fake()->unique()->bothify('IGR/####/###'), 'title' => fake()->sentence(5), 'resolution_text' => fake()->paragraph(), 'resolved_on' => today()->subMonth(), 'due_on' => today()->addMonth(), 'priority' => 'high', 'status' => 'open', 'progress_percentage' => 0, 'created_by' => User::factory()];
    }
}
