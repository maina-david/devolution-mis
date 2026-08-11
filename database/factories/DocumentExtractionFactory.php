<?php

namespace Database\Factories;

use App\Models\DocumentExtraction;
use App\Models\DocumentVersion;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentExtraction>
 */
class DocumentExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_version_id' => DocumentVersion::factory(),
            'status' => 'completed',
            'engine' => 'test-extractor',
            'language' => ReferenceCatalogue::defaultOcrLanguage(),
            'extracted_text' => fake()->paragraphs(3, true),
            'text_checksum_sha256' => fake()->sha256(),
            'character_count' => fake()->numberBetween(100, 2000),
            'page_count' => 1,
            'attempt_count' => 1,
            'metadata' => ['fixture' => true],
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ];
    }
}
