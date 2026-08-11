<?php

namespace Database\Factories;

use App\Models\LearningCertificate;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LearningCertificate>
 */
class LearningCertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_enrollment_id' => LearningEnrollment::factory(), 'certificate_number' => 'IDMIS-LRN-'.fake()->unique()->numerify('########'), 'verification_code' => Str::random(24), 'content_checksum' => hash('sha256', fake()->uuid()), 'final_score' => 80, 'issued_at' => now(), 'issued_by' => User::factory(),
        ];
    }
}
