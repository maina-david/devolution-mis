<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningOfflineSyncFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $learning_offline_package_id
 * @property string $learning_enrollment_id
 * @property string|null $county_id
 * @property string $submitted_by
 * @property string $submitted_by_name
 * @property string|null $reviewed_by
 * @property string|null $reviewed_by_name
 * @property string $client_sync_id
 * @property string $device_id
 * @property string $schema_version
 * @property string $status
 * @property array<string, mixed> $payload
 * @property string $payload_checksum
 * @property string $base_progress_checksum
 * @property string|null $decision_checksum
 * @property int $event_count
 * @property string|null $decision_reason
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $applied_at
 * @property-read LearningOfflinePackage $offlinePackage
 * @property-read LearningEnrollment $enrollment
 * @property-read County|null $county
 * @property-read User $submitter
 * @property-read User|null $reviewer
 */
#[Fillable(['learning_offline_package_id', 'learning_enrollment_id', 'county_id', 'submitted_by', 'submitted_by_name', 'reviewed_by', 'reviewed_by_name', 'client_sync_id', 'device_id', 'schema_version', 'status', 'payload', 'payload_checksum', 'base_progress_checksum', 'decision_checksum', 'event_count', 'decision_reason', 'submitted_at', 'reviewed_at', 'applied_at'])]
class LearningOfflineSync extends Model
{
    /** @use HasFactory<LearningOfflineSyncFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LearningOfflinePackage, $this> */
    public function offlinePackage(): BelongsTo
    {
        return $this->belongsTo(LearningOfflinePackage::class, 'learning_offline_package_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
