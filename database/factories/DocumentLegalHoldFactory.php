<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\DocumentLegalHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLegalHold>
 */
class DocumentLegalHoldFactory extends Factory
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
            'reference' => fake()->unique()->bothify('HOLD-####'),
            'reason' => fake()->sentence(),
            'authority' => 'Office of the Data Controller',
            'placed_by' => User::factory(),
            'placed_at' => now(),
        ];
    }
}
