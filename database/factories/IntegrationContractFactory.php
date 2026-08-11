<?php

namespace Database\Factories;

use App\Models\IntegrationContract;
use App\Models\IntegrationSystem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationContract>
 */
class IntegrationContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['integration_system_id' => IntegrationSystem::factory(), 'submitted_by' => User::factory(), 'version' => 1, 'name' => fake()->sentence(3), 'resource_name' => fake()->slug(2), 'http_method' => 'POST', 'path' => '/v1/'.fake()->slug(), 'request_schema' => ['type' => 'object', 'required' => ['reference'], 'properties' => ['reference' => ['type' => 'string']]], 'response_schema' => ['type' => 'object'], 'required_headers' => ['X-Correlation-ID', 'Idempotency-Key'], 'idempotency_field' => 'reference', 'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => [60, 300, 1800]], 'rate_limit_per_minute' => 60, 'status' => 'review', 'content_checksum' => fake()->sha256()];
    }
}
