<?php

namespace Database\Factories;

use App\Models\LearningEnrollment;
use App\Models\LearningOfflinePackage;
use App\Models\LearningOfflineSync;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningOfflineSync>
 */
class LearningOfflineSyncFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_enrollment_id' => LearningEnrollment::factory(),
            'learning_offline_package_id' => function (array $attributes): string {
                $courseId = LearningEnrollment::query()->whereKey($attributes['learning_enrollment_id'])->sole()->learning_course_id;

                return LearningOfflinePackage::factory()->create(['learning_course_id' => $courseId])->id;
            },
            'county_id' => fn (array $attributes): ?string => LearningEnrollment::query()->whereKey($attributes['learning_enrollment_id'])->sole()->county_id,
            'submitted_by' => fn (array $attributes): string => LearningEnrollment::query()->whereKey($attributes['learning_enrollment_id'])->sole()->user_id,
            'submitted_by_name' => fn (array $attributes): string => User::query()->whereKey($attributes['submitted_by'])->sole()->name,
            'client_sync_id' => fake()->uuid(),
            'device_id' => fake()->uuid(),
            'schema_version' => 'idmis.learning-offline-progress.v1',
            'status' => 'pending',
            'payload' => ['events' => []],
            'payload_checksum' => fake()->sha256(),
            'base_progress_checksum' => fake()->sha256(),
            'event_count' => 1,
            'submitted_at' => now(),
        ];
    }
}
