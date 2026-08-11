<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\CountyGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountyGrant>
 */
class CountyGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $allocated = fake()->numberBetween(50_000_000, 500_000_000);

        return ['county_id' => County::factory(), 'programme' => 'KDSP II', 'financial_year' => '2025/26', 'allocated_amount' => $allocated, 'disbursed_amount' => fake()->numberBetween(0, $allocated), 'status' => fake()->randomElement(['planned', 'processing', 'disbursed'])];
    }
}
