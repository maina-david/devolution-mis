<?php

namespace Database\Factories;

use App\Models\CitizenCase;
use App\Models\CitizenCaseMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitizenCaseMessage>
 */
class CitizenCaseMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['citizen_case_id' => CitizenCase::factory(), 'direction' => 'inbound', 'visibility' => 'public', 'channel' => 'web', 'body' => fake()->paragraph(), 'delivery_status' => 'received', 'posted_at' => now()];
    }
}
