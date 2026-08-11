<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentCorrectivePlan;
use App\Models\AssessmentFinding;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCorrectivePlan>
 */
class AssessmentCorrectivePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['assessment_id' => Assessment::factory(), 'county_id' => County::factory(), 'assessment_finding_id' => AssessmentFinding::factory(), 'submitted_by' => User::factory(), 'reference' => fake()->unique()->bothify('CAP-####'), 'title' => fake()->sentence(5), 'root_cause' => fake()->paragraph(), 'expected_outcome' => fake()->sentence(), 'status' => 'submitted', 'due_at' => now()->addMonth(), 'submitted_at' => now(), 'checksum' => fake()->sha256()];
    }
}
