<?php

namespace Database\Factories;

use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentStandard>
 */
class AssessmentStandardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_thematic_area_id' => AssessmentThematicArea::factory(),
            'code' => fake()->unique()->bothify('S-##'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'norm_reference' => 'Approved sector norm.',
            'weight' => 100,
            'sequence' => 1,
        ];
    }
}
