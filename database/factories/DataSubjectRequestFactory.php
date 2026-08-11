<?php

namespace Database\Factories;

use App\Models\DataSubjectRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSubjectRequest>
 */
class DataSubjectRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assigned_to' => User::factory(),
            'reference' => fake()->unique()->bothify('DSR-2026-######'),
            'request_type' => 'access',
            'requester_name' => fake()->name(),
            'requester_contact' => fake()->safeEmail(),
            'contact_channel' => 'email',
            'scope' => 'Personal information held in the citizen feedback service.',
            'identity_status' => 'pending',
            'status' => 'received',
            'received_at' => now(),
            'due_at' => now()->addDays(30),
            'metadata' => ['intake_channel' => 'service_desk'],
        ];
    }
}
