<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\IndicatorDefinition;
use App\Models\ProjectIndicatorResult;
use App\Models\ProjectProgressUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectIndicatorResult>
 */
class ProjectIndicatorResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_progress_update_id' => ProjectProgressUpdate::factory(),
            'indicator_definition_id' => IndicatorDefinition::factory(),
            'county_id' => County::factory(),
            'period_start' => now()->startOfQuarter(),
            'period_end' => now()->endOfQuarter(),
            'dimension_key' => 'total',
            'disaggregation' => null,
            'numeric_value' => fake()->randomFloat(2, 0, 100),
            'narrative_value' => null,
        ];
    }
}
