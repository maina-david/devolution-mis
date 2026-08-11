<?php

namespace Database\Factories;

use App\Models\IndicatorDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorDefinition>
 */
class IndicatorDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('IND-####'),
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'results_level' => fake()->randomElement(['output', 'outcome', 'impact']),
            'unit_of_measure' => fake()->randomElement(['percent', 'count', 'days']),
            'value_type' => 'number',
            'direction' => 'increase',
            'frequency' => 'quarterly',
            'disaggregation_dimensions' => ['sex', 'age_group'],
            'data_source' => fake()->sentence(),
            'verification_method' => fake()->sentence(),
            'status' => 'approved',
            'effective_from' => now()->startOfYear(),
            'created_by' => User::factory(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
    }
}
