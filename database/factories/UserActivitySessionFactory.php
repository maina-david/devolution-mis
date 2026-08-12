<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserActivitySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserActivitySession>
 */
class UserActivitySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_fingerprint' => hash('sha256', fake()->uuid()),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'current_route' => 'dashboard',
            'current_path' => '/dashboard',
            'current_page_title' => 'Dashboard',
            'last_method' => 'GET',
            'last_action' => 'page.viewed',
            'logged_in_at' => now()->subMinutes(10),
            'last_seen_at' => now(),
        ];
    }
}
