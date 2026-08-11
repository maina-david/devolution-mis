<?php

namespace Database\Factories;

use App\Models\DevolutionProject;
use App\Models\ProjectScheduleBaseline;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectScheduleBaseline>
 */
class ProjectScheduleBaselineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'devolution_project_id' => DevolutionProject::factory(),
            'version' => 1,
            'status' => 'pending',
            'schedule_snapshot' => [],
            'critical_path_analysis' => [],
            'snapshot_checksum' => hash('sha256', 'project-schedule-baseline'),
            'baseline_reason' => 'Initial approved delivery schedule captured after planning review.',
            'requested_by' => User::factory(),
        ];
    }
}
