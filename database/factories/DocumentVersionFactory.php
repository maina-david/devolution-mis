<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_document_id' => AssessmentDocument::factory(),
            'version_number' => 1,
            'storage_disk' => 'local',
            'path' => 'assessment-evidence/'.fake()->uuid().'.pdf',
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 100000),
            'content_checksum' => fake()->sha256(),
            'scan_status' => 'clean',
            'scan_details' => ['engine' => 'test'],
            'scanned_at' => now(),
            'ocr_status' => 'not_requested',
            'change_summary' => 'Initial upload',
            'uploaded_by' => User::factory(),
        ];
    }
}
