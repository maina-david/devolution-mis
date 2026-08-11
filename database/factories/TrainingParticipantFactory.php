<?php

namespace Database\Factories;

use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingParticipant>
 */
class TrainingParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_cohort_id' => TrainingCohort::factory(), 'participant_reference' => fake()->unique()->bothify('TRN-#####'), 'role_title' => fake()->jobTitle(),
        ];
    }
}
