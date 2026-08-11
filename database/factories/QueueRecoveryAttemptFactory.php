<?php

namespace Database\Factories;

use App\Models\QueueRecoveryAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueueRecoveryAttempt>
 */
class QueueRecoveryAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'failed_job_uuid' => (string) Str::uuid(),
            'initiated_by' => User::factory(),
            'initiated_by_name' => fake()->name(),
            'connection' => 'database',
            'queue' => 'default',
            'job_name' => 'App\\Jobs\\GenerateScheduledReport',
            'payload_checksum' => hash('sha256', fake()->uuid()),
            'exception_checksum' => hash('sha256', fake()->uuid()),
            'outcome' => 'requeued',
            'failed_at' => now()->subMinute(),
            'attempted_at' => now(),
            'evidence_checksum' => hash('sha256', fake()->unique()->uuid()),
        ];
    }
}
