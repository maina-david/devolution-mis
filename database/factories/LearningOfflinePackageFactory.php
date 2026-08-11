<?php

namespace Database\Factories;

use App\Models\LearningCourse;
use App\Models\LearningOfflinePackage;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningOfflinePackage>
 */
class LearningOfflinePackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_course_id' => LearningCourse::factory(),
            'generated_by' => User::factory(),
            'package_version' => 1,
            'status' => 'ready',
            'locale' => ReferenceCatalogue::defaultLanguage(),
            'storage_disk' => 'local',
            'path' => 'learning/offline/test.zip',
            'original_name' => 'course-offline-v1.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => 1024,
            'content_checksum' => fake()->sha256(),
            'manifest_checksum' => fake()->sha256(),
            'course_content_checksum' => fake()->sha256(),
            'manifest_summary' => ['modules' => 1, 'lessons' => 1, 'assets' => 0],
            'generated_at' => now(),
        ];
    }
}
