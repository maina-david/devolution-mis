<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationFindingActionUpdate>
 */
class EvaluationFindingActionUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['evaluation_finding_action_id' => EvaluationFindingAction::factory(), 'assessment_document_id' => AssessmentDocument::factory(), 'submitted_by' => User::factory(), 'progress_percentage' => 50, 'narrative' => fake()->paragraph(), 'submitted_at' => now(), 'checksum' => fake()->sha256()];
    }
}
