<?php

namespace Database\Factories;

use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IntegrationExchange>
 */
class IntegrationExchangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['integration_contract_id' => IntegrationContract::factory(), 'direction' => 'outbound', 'correlation_id' => Str::uuid(), 'idempotency_key' => fake()->uuid(), 'request_payload' => ['reference' => fake()->uuid()], 'request_headers' => [], 'payload_checksum' => fake()->sha256(), 'status' => 'succeeded', 'http_status' => 202, 'attempt_count' => 1, 'accepted_at' => now(), 'processed_at' => now(), 'completed_at' => now()];
    }
}
