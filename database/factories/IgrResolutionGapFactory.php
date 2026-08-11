<?php

namespace Database\Factories;

use App\Models\IgrGapCategory;
use App\Models\IgrResolution;
use App\Models\IgrResolutionGap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrResolutionGap>
 */
class IgrResolutionGapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'igr_resolution_id' => IgrResolution::factory(),
            'igr_gap_category_id' => IgrGapCategory::factory(),
            'owner_user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'impact' => fake()->sentence(12),
            'severity' => 'medium',
            'status' => 'open',
            'due_on' => today()->addWeeks(2),
            'reported_by' => User::factory(),
        ];
    }
}
