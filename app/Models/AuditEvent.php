<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $actor_id
 * @property string|null $county_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $action
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property Carbon|null $occurred_at
 * @property string|null $previous_hash
 * @property string|null $event_hash
 * @property int|null $hash_version
 */
#[Fillable(['actor_id', 'county_id', 'subject_type', 'subject_id', 'action', 'description', 'metadata', 'ip_address', 'occurred_at', 'previous_hash', 'event_hash', 'hash_version'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime', 'hash_version' => 'integer'];
    }
}
