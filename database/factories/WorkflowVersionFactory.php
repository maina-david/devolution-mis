<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVersion>
 */
class WorkflowVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'version' => 1,
            'status' => 'draft',
            'configuration' => [
                'initial_state' => 'draft',
                'states' => ['draft', 'submitted', 'approved'],
                'transitions' => [
                    ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted'],
                    ['name' => 'approve', 'from' => 'submitted', 'to' => 'approved'],
                ],
                'rules' => [],
            ],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'checksum' => hash('sha256', json_encode($attributes['configuration'], JSON_THROW_ON_ERROR)),
            'effective_from' => now(),
            'published_by' => User::factory(),
            'published_at' => now(),
        ]);
    }
}
