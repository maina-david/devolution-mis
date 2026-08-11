<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assessed = fake()->boolean(70);

        return ['county_id' => County::factory(), 'cycle' => fake()->randomElement(['2023/24 ACPA', '2024/25 ACPA', '2025/26 ACPA']), 'status' => $assessed ? AssessmentStatus::Assessed : AssessmentStatus::EvidenceCollection, 'score' => $assessed ? fake()->randomFloat(2, 45, 96) : null, 'assessor_id' => null, 'assessed_at' => $assessed ? fake()->dateTimeBetween('-1 year') : null];
    }
}
