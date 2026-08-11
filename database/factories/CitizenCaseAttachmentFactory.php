<?php

namespace Database\Factories;

use App\Models\CitizenCase;
use App\Models\CitizenCaseAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitizenCaseAttachment>
 */
class CitizenCaseAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['citizen_case_id' => CitizenCase::factory(), 'title' => fake()->words(3, true), 'original_name' => 'evidence.pdf', 'path' => 'citizen-cases/test/evidence.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024, 'checksum_sha256' => hash('sha256', 'test'), 'source_type' => 'born_digital', 'scan_status' => 'clean', 'scan_details' => ['engine' => 'test'], 'ocr_status' => 'not_required'];
    }
}
