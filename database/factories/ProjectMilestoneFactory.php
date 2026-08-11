<?php

namespace Database\Factories;

use App\Models\DevolutionProject;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
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
            'code' => fake()->unique()->bothify('MS-###'),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'planned_start_date' => today(),
            'planned_end_date' => today()->addMonth(),
            'weight' => 100,
            'progress' => 0,
            'status' => 'planned',
            'dependencies' => [],
        ];
    }
}
