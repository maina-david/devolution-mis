<?php

namespace App\Models;

use Database\Factories\ReleaseRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $deployed_at
 * @property Carbon|null $validated_at
 * @property Carbon|null $rolled_back_at
 */
#[Fillable(['deployed_by', 'validated_by', 'rolled_back_by', 'version', 'git_sha', 'environment', 'artifact_checksum', 'change_reference', 'migration_batch', 'status', 'deployed_at', 'validated_at', 'rolled_back_at', 'rollback_to_version', 'notes'])]
class ReleaseRecord extends Model
{
    /** @use HasFactory<ReleaseRecordFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['deployed_at' => 'immutable_datetime', 'validated_at' => 'immutable_datetime', 'rolled_back_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function deployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rollbackActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}
