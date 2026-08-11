<?php

namespace Database\Factories;

use App\Models\LearningCohort;
use App\Models\LearningCohortMembership;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCohortMembership>
 */
class LearningCohortMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['learning_cohort_id' => LearningCohort::factory(), 'learning_enrollment_id' => LearningEnrollment::factory(), 'added_by' => User::factory(), 'joined_at' => now()];
    }
}
