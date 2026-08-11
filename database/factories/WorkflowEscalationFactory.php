<?php

namespace Database\Factories;

use App\Models\WorkflowEscalation;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowEscalation>
 */
class WorkflowEscalationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_instance_id' => WorkflowInstance::factory(),
            'level' => 1,
            'reason' => 'sla_breach',
            'status' => 'open',
            'due_at' => now()->subHour(),
            'state_entered_at' => now()->subHours(2),
            'triggered_at' => now(),
            'metadata' => [],
        ];
    }
}
