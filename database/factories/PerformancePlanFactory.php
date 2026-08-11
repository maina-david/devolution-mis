<?php

namespace Database\Factories;

use App\Models\PerformanceCycle;
use App\Models\PerformancePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformancePlan>
 */
class PerformancePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_cycle_id' => PerformanceCycle::factory(), 'employee_id' => User::factory(), 'supervisor_id' => User::factory(), 'plan_type' => 'individual',
            'hris_employee_reference' => fake()->bothify('IPPD-######'), 'job_title' => fake()->jobTitle(), 'overall_expectations' => fake()->paragraph(), 'status' => 'draft', 'integration_status' => 'referenced', 'created_by' => User::factory(),
        ];
    }
}
