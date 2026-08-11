<?php

namespace Database\Factories;

use App\Models\LearningEnrollment;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Models\VirtualClassroomAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualClassroomAttendance>
 */
class VirtualClassroomAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $joinedAt = now()->subHours(2);

        return ['virtual_classroom_id' => VirtualClassroom::factory(), 'learning_enrollment_id' => LearningEnrollment::factory(), 'user_id' => User::factory(), 'attendance_status' => 'present', 'joined_at' => $joinedAt, 'left_at' => $joinedAt->copy()->addMinutes(90), 'attended_minutes' => 90, 'source' => 'manual', 'payload_checksum' => hash('sha256', fake()->uuid()), 'recorded_by' => User::factory(), 'recorded_at' => now()];
    }
}
