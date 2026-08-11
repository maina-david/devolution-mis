<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\DocumentDisposition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentDisposition>
 */
class DocumentDispositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['assessment_document_id' => AssessmentDocument::factory(), 'requested_by' => User::factory(), 'action' => 'secure_destroy', 'reason' => fake()->sentence(), 'authority_reference' => fake()->bothify('RET-####'), 'retention_due_at' => now()->subDay(), 'scheduled_for' => now(), 'status' => 'pending'];
    }
}
