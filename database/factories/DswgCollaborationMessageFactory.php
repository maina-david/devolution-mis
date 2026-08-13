<?php

namespace Database\Factories;

use App\Models\DswgCollaborationMessage;
use App\Models\DswgCollaborationThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DswgCollaborationMessage>
 */
class DswgCollaborationMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dswg_collaboration_thread_id' => DswgCollaborationThread::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'checksum' => hash('sha256', Str::uuid()->toString()),
            'posted_at' => now(),
        ];
    }
}
