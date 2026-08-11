<?php

namespace Database\Factories;

use App\Models\AssessmentFunction;
use App\Models\AssessmentThematicArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentThematicArea>
 */
class AssessmentThematicAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_function_id' => AssessmentFunction::factory(),
            'code' => fake()->unique()->bothify('T-##'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'weight' => 100,
            'sequence' => 1,
        ];
    }
}
