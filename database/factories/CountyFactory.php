<?php

namespace Database\Factories;

use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<County>
 */
class CountyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' County';

        return ['code' => fake()->unique()->numberBetween(1, 255), 'name' => $name, 'slug' => Str::slug($name), 'region' => fake()->randomElement(['Central', 'Coast', 'Eastern', 'Nairobi', 'North Eastern', 'Nyanza', 'Rift Valley', 'Western']), 'map_x' => fake()->randomFloat(2, 8, 92), 'map_y' => fake()->randomFloat(2, 8, 92)];
    }
}
