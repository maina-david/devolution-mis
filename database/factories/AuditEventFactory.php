<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['actor_id' => User::factory(), 'county_id' => County::factory(), 'action' => fake()->randomElement(['assessment.submitted', 'evidence.uploaded', 'grant.updated']), 'description' => fake()->sentence(), 'metadata' => [], 'ip_address' => fake()->ipv4(), 'occurred_at' => now()];
    }
}
