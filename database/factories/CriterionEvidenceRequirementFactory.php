<?php

namespace Database\Factories;

use App\Models\AssessmentCriterion;
use App\Models\CriterionEvidenceRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriterionEvidenceRequirement>
 */
class CriterionEvidenceRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_criterion_id' => AssessmentCriterion::factory(),
            'code' => fake()->unique()->bothify('E-##'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'minimum_documents' => 1,
            'allowed_categories' => ['policy', 'report'],
            'accepted_mime_types' => ['application/pdf'],
            'requires_verification' => true,
            'is_mandatory' => true,
        ];
    }
}
