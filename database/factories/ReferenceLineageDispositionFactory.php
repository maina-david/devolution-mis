<?php

namespace Database\Factories;

use App\Models\ReferenceLineageDisposition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceLineageDisposition>
 */
class ReferenceLineageDispositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RLD-'.now()->format('Y').'-'.fake()->unique()->regexify('[A-Z0-9]{10}'),
            'record_type' => 'assessment',
            'record_id' => fake()->uuid(),
            'decision' => 'retain_legacy',
            'reference_data_release_id' => null,
            'record_snapshot' => ['id' => fake()->uuid(), 'reference' => fake()->bothify('ACPA-####')],
            'record_checksum' => fake()->sha256(),
            'business_reason' => fake()->sentence(12),
            'source_reference' => fake()->bothify('LEGACY-####'),
            'status' => 'proposed',
            'proposed_by' => User::factory(),
            'decision_checksum' => fake()->sha256(),
        ];
    }
}
