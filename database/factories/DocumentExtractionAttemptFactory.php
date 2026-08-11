<?php

namespace Database\Factories;

use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionAttempt;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentExtractionAttempt> */
class DocumentExtractionAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_extraction_id' => DocumentExtraction::factory(),
            'document_version_id' => fn (array $attributes): string => (string) DocumentExtraction::query()->whereKey($attributes['document_extraction_id'])->value('document_version_id'),
            'attempt_number' => 1,
            'trigger_source' => 'upload',
            'status' => 'completed',
            'engine' => 'test-extractor',
            'language' => ReferenceCatalogue::defaultOcrLanguage(),
            'text_checksum_sha256' => fake()->sha256(),
            'character_count' => fake()->numberBetween(100, 2000),
            'page_count' => 1,
            'metadata' => ['fixture' => true],
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'duration_ms' => 1000,
        ];
    }
}
