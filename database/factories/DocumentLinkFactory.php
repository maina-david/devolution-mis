<?php

namespace Database\Factories;

use App\Models\AssessmentDocument;
use App\Models\DocumentLink;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLink>
 */
class DocumentLinkFactory extends Factory
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
            'subject_type' => (new TravelRequest)->getMorphClass(),
            'subject_id' => TravelRequest::factory(),
            'purpose' => 'supporting-document',
            'created_by' => User::factory(),
        ];
    }
}
