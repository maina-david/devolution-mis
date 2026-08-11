<?php

namespace App\Models;

use Database\Factories\QueueRecoveryAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $failed_job_uuid
 * @property string $initiated_by
 * @property string $initiated_by_name
 * @property string $connection
 * @property string $queue
 * @property string $job_name
 * @property string $payload_checksum
 * @property string $exception_checksum
 * @property string $outcome
 * @property string|null $error_category
 * @property string|null $error_detail
 * @property Carbon $failed_at
 * @property Carbon $attempted_at
 * @property string $evidence_checksum
 */
#[Fillable(['failed_job_uuid', 'initiated_by', 'initiated_by_name', 'connection', 'queue', 'job_name', 'payload_checksum', 'exception_checksum', 'outcome', 'error_category', 'error_detail', 'failed_at', 'attempted_at', 'evidence_checksum'])]
class QueueRecoveryAttempt extends Model
{
    /** @use HasFactory<QueueRecoveryAttemptFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['failed_at' => 'immutable_datetime', 'attempted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
