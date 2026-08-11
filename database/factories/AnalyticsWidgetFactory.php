<?php

namespace Database\Factories;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticsWidget>
 */
class AnalyticsWidgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analytics_dashboard_id' => AnalyticsDashboard::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'metric_key' => fake()->randomElement(['counties.total', 'projects.active', 'assessments.published']),
            'visualization' => 'metric',
            'disaggregation' => null,
            'filters' => [],
            'position' => 1,
            'width' => 1,
        ];
    }
}
