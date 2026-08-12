<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPageView>
 */
class UserPageViewFactory extends Factory
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
            'route_name' => 'dashboard',
            'path' => '/dashboard',
            'page_title' => 'Dashboard',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'viewed_at' => now(),
        ];
    }
}
