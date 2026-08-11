<?php

namespace Database\Factories;

use App\Models\TrainingAssessment;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingAssessment>
 */
class TrainingAssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_participant_id' => TrainingParticipant::factory(), 'assessed_by' => User::factory()->platformAdmin(), 'assessment_type' => 'post_training', 'score' => 80, 'outcome' => 'competent', 'feedback' => fake()->paragraph(), 'evidence_references' => ['TEST-EVIDENCE'], 'assessed_at' => now(),
        ];
    }
}
