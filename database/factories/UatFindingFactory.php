<?php

namespace Database\Factories;

use App\Models\UatExecution;
use App\Models\UatFinding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UatFinding>
 */
class UatFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uat_execution_id' => UatExecution::factory(),
            'raised_by' => User::factory(),
            'owner_id' => User::factory(),
            'severity' => 'medium',
            'title' => 'Representative journey did not meet the expected result',
            'description' => 'The observed result differed from the governed scenario expectation and requires corrective action plus independent verification.',
            'status' => 'open',
            'due_on' => now()->addWeek()->toDateString(),
        ];
    }
}
