<?php

namespace Database\Factories;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'code' => 'RPT-'.fake()->unique()->numerify('#####'),
            'name' => fake()->sentence(4),
            'workspace' => 'analytics-dashboard',
            'format' => 'pdf',
            'frequency' => 'monthly',
            'filters' => [],
            'recipient_user_ids' => [],
            'status' => 'draft',
            'next_run_at' => now()->addDay(),
        ];
    }
}
