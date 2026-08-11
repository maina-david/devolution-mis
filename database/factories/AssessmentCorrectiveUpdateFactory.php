<?php

namespace Database\Factories;

use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentCorrectiveUpdate>
 */
class AssessmentCorrectiveUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['assessment_corrective_action_id' => AssessmentCorrectiveAction::factory(), 'assessment_document_id' => AssessmentDocument::factory(), 'submitted_by' => User::factory(), 'progress_percentage' => 50, 'narrative' => fake()->paragraph(), 'status' => 'pending_verification', 'submitted_at' => now(), 'checksum' => fake()->sha256()];
    }
}
