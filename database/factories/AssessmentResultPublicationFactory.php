<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentResultPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentResultPublication>
 */
class AssessmentResultPublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'assessment_cycle_id' => fn (array $attributes) => Assessment::query()->whereKey($attributes['assessment_id'])->value('assessment_cycle_id'),
            'assessment_scorecard_version_id' => fn (array $attributes) => Assessment::query()->whereKey($attributes['assessment_id'])->value('assessment_scorecard_version_id'),
            'county_id' => fn (array $attributes) => Assessment::query()->whereKey($attributes['assessment_id'])->value('county_id'),
            'score' => 75,
            'performance_band' => 'Meets standard',
            'function_profile' => [],
            'calculation_snapshot' => [],
            'checksum' => fake()->unique()->sha256(),
            'published_by' => User::factory(),
            'published_at' => now(),
        ];
    }
}
