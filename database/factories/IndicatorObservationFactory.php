<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorObservation>
 */
class IndicatorObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_definition_id' => IndicatorDefinition::factory(),
            'county_id' => County::factory(),
            'programme_id' => Programme::factory(),
            'period_start' => now()->startOfQuarter()->toDateString(),
            'period_end' => now()->endOfQuarter()->toDateString(),
            'measure_type' => 'actual',
            'dimension_key' => 'total',
            'numeric_value' => fake()->randomFloat(2, 0, 100),
            'source_reference' => fake()->url(),
            'provenance' => ['source_system' => 'county-mis', 'captured_at' => now()->toIso8601String()],
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ];
    }
}
