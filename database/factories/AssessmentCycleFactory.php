<?php

namespace Database\Factories;

use App\Models\AssessmentCycle;
use App\Models\AssessmentScorecardVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCycle>
 */
class AssessmentCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('ACPA-####'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'assessment_scorecard_version_id' => AssessmentScorecardVersion::factory()->state(['status' => 'published', 'checksum' => fake()->sha256(), 'published_at' => now(), 'effective_from' => now()]),
            'period_start' => today()->startOfYear(),
            'period_end' => today()->endOfYear(),
            'submission_opens_at' => now(),
            'submission_closes_at' => now()->addMonths(3),
            'status' => 'open',
        ];
    }
}
