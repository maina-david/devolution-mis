<?php

namespace App\Models;

use Database\Factories\OperationalBackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $restore_verified_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['initiated_by', 'restore_verified_by', 'reference', 'disk', 'path', 'database_name', 'format', 'sha256', 'size_bytes', 'status', 'started_at', 'completed_at', 'restore_verified_at', 'restore_duration_ms', 'verified_table_count', 'restore_manifest_checksum', 'error_detail', 'metadata'])]
class OperationalBackup extends Model
{
    /** @use HasFactory<OperationalBackupFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'restore_verified_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function restoreVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restore_verified_by');
    }
}
