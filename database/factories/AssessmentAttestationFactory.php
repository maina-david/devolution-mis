<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAttestation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAttestation>
 */
class AssessmentAttestationFactory extends Factory
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
            'attested_by' => User::factory(),
            'attestor_title' => 'County Secretary',
            'statement' => 'I attest that this submission is complete and accurate.',
            'content_checksum' => fake()->sha256(),
            'attested_at' => now(),
        ];
    }
}
