<?php

namespace Database\Factories;

use App\Models\RetentionSchedule;
use App\Models\RetentionScheduleApproval;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionScheduleApproval>
 */
class RetentionScheduleApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'retention_schedule_id' => RetentionSchedule::factory()->state(['status' => 'submitted']),
            'submitted_by' => User::factory(),
            'status' => 'submitted',
            'snapshot_checksum' => fn (array $attributes): string => app(CanonicalJson::class)->checksum(
                RetentionSchedule::query()
                    ->whereKey($attributes['retention_schedule_id'])
                    ->firstOrFail()
                    ->approvalSnapshot()
            ),
            'submitted_at' => now(),
        ];
    }
}
