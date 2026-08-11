<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_data_release_id' => ReferenceDataRelease::factory(),
            'requester_id' => User::factory()->countyOfficial(),
            'county_id' => County::factory(),
            'reference' => 'SUP-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
            'category' => 'service_request',
            'priority' => 'medium',
            'channel' => 'web',
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(3),
            'status' => 'open',
            'requested_at' => now(),
            'first_response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addHours(40),
            'last_activity_at' => now(),
        ];
    }

    public function assigned(User $assignee): static
    {
        return $this->state(fn (): array => ['assigned_to' => $assignee->id, 'status' => 'triaged', 'first_responded_at' => now()]);
    }
}
