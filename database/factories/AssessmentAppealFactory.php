<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAppeal>
 */
class AssessmentAppealFactory extends Factory
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
            'appellant_id' => User::factory(),
            'grounds' => fake()->paragraph(),
            'requested_remedy' => fake()->sentence(),
            'submitted_at' => now(),
        ];
    }
}
