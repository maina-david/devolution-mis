<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTransition>
 */
class WorkflowTransitionFactory extends Factory
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
            'transition_name' => 'start',
            'from_state' => null,
            'to_state' => 'draft',
            'actor_id' => User::factory(),
            'comment' => null,
            'rule_evaluation' => ['passed' => true, 'results' => []],
            'context_snapshot' => [],
            'occurred_at' => now(),
        ];
    }
}
