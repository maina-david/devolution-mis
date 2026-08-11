<?php

namespace Database\Factories;

use App\Models\ReconciliationException;
use App\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationException>
 */
class ReconciliationExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['reconciliation_run_id' => ReconciliationRun::factory(), 'external_reference' => fake()->uuid(), 'exception_type' => 'value_mismatch', 'field_name' => 'status', 'severity' => 'medium', 'expected_value' => 'active', 'actual_value' => 'inactive', 'description' => fake()->sentence(), 'status' => 'open'];
    }
}
