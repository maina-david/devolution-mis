<?php

namespace Database\Factories;

use App\Models\ProjectMilestone;
use App\Models\ProjectResource;
use App\Models\ProjectResourceAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectResourceAllocation>
 */
class ProjectResourceAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_resource_id' => ProjectResource::factory(),
            'project_milestone_id' => ProjectMilestone::factory(),
            'starts_on' => today(),
            'ends_on' => today()->addDays(4),
            'planned_units_per_day' => 4,
            'planned_units' => 20,
            'planned_cost' => 50000,
            'allocation_checksum' => hash('sha256', Str::uuid()->toString()),
            'created_by' => User::factory(),
        ];
    }
}
