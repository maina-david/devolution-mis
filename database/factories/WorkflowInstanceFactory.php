<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory()->published(),
            'current_state' => 'draft',
            'status' => 'active',
            'context' => [],
            'started_by' => User::factory(),
            'started_at' => now(),
            'state_entered_at' => now(),
        ];
    }
}
