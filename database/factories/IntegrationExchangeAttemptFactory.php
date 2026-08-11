<?php

namespace Database\Factories;

use App\Models\IntegrationExchange;
use App\Models\IntegrationExchangeAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationExchangeAttempt>
 */
class IntegrationExchangeAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration_exchange_id' => IntegrationExchange::factory(),
            'attempt_number' => 1,
            'trigger_source' => 'initial_dispatch',
            'outcome' => 'succeeded',
            'http_status' => 202,
            'retryable' => false,
            'response_checksum' => fake()->sha256(),
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'duration_ms' => 100,
        ];
    }
}
