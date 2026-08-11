<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'group' => 'operations', 'label' => fake()->words(3, true), 'value' => 'disabled', 'type' => 'select', 'description' => fake()->sentence()];
    }
}
