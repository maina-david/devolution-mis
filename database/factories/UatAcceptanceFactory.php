<?php

namespace Database\Factories;

use App\Models\UatAcceptance;
use App\Models\UatCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UatAcceptance>
 */
class UatAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uat_campaign_id' => UatCampaign::factory(),
            'submitted_by' => User::factory(),
            'decision' => 'pending',
            'criteria_snapshot' => ['Every required scenario/county pair has a passing latest execution.'],
            'coverage_snapshot' => ['county_ids' => [], 'scenario_ids' => [], 'required_pairs' => 0, 'passing_pairs' => 0, 'roles' => []],
            'open_findings_count' => 0,
            'checksum' => hash('sha256', Str::uuid()->toString()),
            'submitted_at' => now(),
        ];
    }
}
