<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningOfflinePackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $learning_course_id
 * @property string $generated_by
 * @property int $package_version
 * @property string $status
 * @property string $locale
 * @property string|null $storage_disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $content_checksum
 * @property string|null $manifest_checksum
 * @property string $course_content_checksum
 * @property array<string, mixed>|null $manifest_summary
 * @property CarbonImmutable|null $generated_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $failure_message
 * @property LearningCourse $course
 * @property User $generator
 */
#[Fillable(['learning_course_id', 'generated_by', 'package_version', 'status', 'locale', 'storage_disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'content_checksum', 'manifest_checksum', 'course_content_checksum', 'manifest_summary', 'generated_at', 'failed_at', 'failure_message'])]
class LearningOfflinePackage extends Model
{
    /** @use HasFactory<LearningOfflinePackageFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'generating'];

    protected function casts(): array
    {
        return ['manifest_summary' => 'array', 'generated_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return HasMany<LearningOfflineSync, $this> */
    public function offlineSyncs(): HasMany
    {
        return $this->hasMany(LearningOfflineSync::class);
    }
}
