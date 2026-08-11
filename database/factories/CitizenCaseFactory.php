<?php

namespace Database\Factories;

use App\Models\CitizenCase;
use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CitizenCase>
 */
class CitizenCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['reference' => fake()->unique()->bothify('CFM-2026-########'), 'tracking_token_hash' => hash('sha256', Str::random(48)), 'case_type' => 'feedback', 'category' => 'complaint', 'channel' => 'web', 'county_id' => County::factory(), 'subject' => fake()->sentence(6), 'description' => fake()->paragraph(), 'citizen_name' => fake()->name(), 'citizen_email' => fake()->safeEmail(), 'is_anonymous' => false, 'preferred_contact' => 'email', 'consent_given' => true, 'consent_recorded_at' => now(), 'privacy_notice_version' => '2026-08', 'priority' => 'medium', 'status' => 'received', 'is_sensitive' => false, 'first_response_due_at' => now()->addDay(), 'resolution_due_at' => now()->addDays(10), 'source_metadata' => ['channel' => 'web']];
    }
}
