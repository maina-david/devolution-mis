<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\UatExecution;
use App\Models\UatScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UatExecution>
 */
class UatExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $completedAt = now()->subDay();
        $evidence = ['reference' => 'UAT-EVIDENCE-'.Str::upper(Str::random(8))];

        return [
            'uat_scenario_id' => UatScenario::factory(),
            'county_id' => County::factory(),
            'tested_by' => User::factory(),
            'environment' => 'government-hosting-uat',
            'outcome' => 'pass',
            'actual_result' => 'The representative journey completed and the expected authorization, evidence and audit controls were observed.',
            'evidence_references' => [$evidence['reference']],
            'started_at' => $completedAt->copy()->subMinutes(30),
            'completed_at' => $completedAt,
            'checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
        ];
    }
}
