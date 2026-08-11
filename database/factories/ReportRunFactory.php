<?php

namespace Database\Factories;

use App\Models\ReportRun;
use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportRun>
 */
class ReportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_schedule_id' => ReportSchedule::factory(),
            'status' => 'queued',
            'filter_snapshot' => [],
        ];
    }
}
