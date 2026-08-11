<?php

namespace Database\Factories;

use App\Models\DataAsset;
use App\Models\ProcessingActivity;
use App\Models\RetentionSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessingActivity>
 */
class ProcessingActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_asset_id' => DataAsset::factory(),
            'retention_schedule_id' => RetentionSchedule::factory(),
            'submitted_by' => User::factory(),
            'reviewed_by' => User::factory(),
            'reference' => fake()->unique()->bothify('ROPA-2026-####'),
            'name' => fake()->words(4, true),
            'purpose' => fake()->paragraph(),
            'lawful_basis' => 'public_task',
            'lawful_basis_reference' => 'Constitution of Kenya and applicable devolution mandate; legal validation pending.',
            'controller_name' => 'State Department for Devolution',
            'processor_names' => [],
            'recipient_categories' => ['authorized_devolution_officials'],
            'processing_operations' => ['collect', 'store', 'verify', 'report'],
            'automated_decision_making' => false,
            'cross_border_transfer' => false,
            'dpia_status' => 'required',
            'risk_summary' => 'Unauthorized disclosure and excessive access require scoped authorization and audit.',
            'security_measures' => 'County-scoped RBAC, MFA boundary, encryption, private documents and immutable audit.',
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'next_review_at' => now()->addYear(),
        ];
    }
}
