<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationFindingUpdate>
 */
class EvaluationFindingUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['evaluation_finding_id' => EvaluationFinding::factory(), 'assessment_document_id' => AssessmentDocument::factory(), 'submitted_by' => User::factory(), 'progress_percentage' => fake()->numberBetween(1, 100), 'narrative' => fake()->paragraph(), 'submitted_at' => now(), 'checksum' => fake()->sha256()];
    }
}
