<?php

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Models\User;
use App\Models\UserLocalePreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLocalePreference>
 */
class UserLocalePreferenceFactory extends Factory
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
            'locale' => fake()->randomElement(SupportedLocale::cases()),
        ];
    }
}
