<?php

namespace Database\Factories;

use App\Models\DswgMeetingSeries;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DswgMeetingSeries>
 */
class DswgMeetingSeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dswg_working_group_id' => DswgWorkingGroup::factory(),
            'reference_prefix' => fake()->unique()->bothify('DSWG-SERIES-####'),
            'title' => 'Quarterly sector delivery review',
            'frequency' => 'quarterly',
            'interval' => 1,
            'next_occurrence_at' => now()->addWeek(),
            'ends_on' => today()->addYear(),
            'duration_minutes' => 120,
            'timezone' => config('app.business_timezone'),
            'meeting_mode' => 'hybrid',
            'venue' => 'State Department conference room',
            'virtual_link' => 'https://meet.example.org/dswg-series',
            'agenda' => 'Review delivery performance, decisions, risks and accountable actions.',
            'quorum_required' => 1,
            'generation_horizon_days' => 90,
            'next_sequence' => 1,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
