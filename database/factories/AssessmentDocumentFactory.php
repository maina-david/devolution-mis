<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentDocument>
 */
class AssessmentDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['assessment_id' => Assessment::factory(), 'county_id' => fn (array $attributes) => Assessment::query()->findOrFail((string) $attributes['assessment_id'])->county_id, 'category' => fake()->randomElement(['ADP', 'CIDP', 'Audit opinion', 'Public participation']), 'title' => fake()->sentence(5), 'path' => 'assessment-evidence/'.fake()->uuid().'.pdf', 'original_name' => 'evidence.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => fake()->numberBetween(10_000, 2_000_000), 'content_checksum' => fake()->sha256(), 'scan_status' => 'clean', 'ocr_status' => 'not_requested', 'security_classification' => 'official', 'record_status' => 'active', 'document_date' => fake()->date(), 'verification_status' => fake()->randomElement(['pending', 'verified', 'rejected']), 'uploaded_by' => null];
    }
}
