<?php

namespace Database\Factories;

use App\Models\TravelItinerary;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelItinerary>
 */
class TravelItineraryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'travel_request_id' => TravelRequest::factory(),
            'sequence' => 1,
            'origin' => 'Nairobi',
            'destination' => fake()->city(),
            'departs_at' => now()->addWeeks(2)->setTime(8, 0),
            'arrives_at' => now()->addWeeks(2)->setTime(10, 0),
            'transport_mode' => 'road',
            'carrier' => 'Government transport',
            'estimated_cost' => fake()->numberBetween(5000, 50000),
        ];
    }
}
