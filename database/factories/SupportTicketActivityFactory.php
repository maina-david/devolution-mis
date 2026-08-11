<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicketActivity>
 */
class SupportTicketActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'actor_name' => 'system:test',
            'activity_type' => 'created',
            'from_status' => 'none',
            'to_status' => 'open',
            'narrative' => fake()->sentence(12),
            'metadata' => [],
            'occurred_at' => now(),
            'evidence_checksum' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
