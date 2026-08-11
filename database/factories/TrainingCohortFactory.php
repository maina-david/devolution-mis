<?php

namespace Database\Factories;

use App\Models\RolloutWave;
use App\Models\TrainingCohort;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingCohort>
 */
class TrainingCohortFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rollout_wave_id' => RolloutWave::factory(), 'code' => fake()->unique()->bothify('COHORT-####'), 'name' => fake()->sentence(3), 'audience_role' => 'county-official', 'delivery_mode' => 'blended', 'language' => ReferenceCatalogue::defaultLanguage(), 'seat_capacity' => 24, 'minimum_attendance_hours' => 6, 'passing_score' => 70, 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDay(),
        ];
    }
}
