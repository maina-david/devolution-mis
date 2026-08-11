<?php

namespace Database\Factories;

use App\Models\RolloutWave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolloutWave>
 */
class RolloutWaveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->devolutionAdmin(), 'code' => fake()->unique()->bothify('WAVE-####'), 'name' => fake()->sentence(3), 'objective' => fake()->paragraph(), 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonths(2), 'planned_participants' => 24, 'entry_criteria' => ['Sponsor named', 'Connectivity checked'], 'support_channels' => ['Help desk'],
        ];
    }
}
