<?php

namespace App\Models;

use Database\Factories\AuditAssuranceRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $environment
 * @property string $outcome
 * @property int $event_count
 * @property int $verified_event_count
 * @property int $legacy_event_count
 * @property int $mismatch_count
 * @property string|null $first_event_id
 * @property string|null $last_event_id
 * @property string|null $first_event_hash
 * @property string|null $last_event_hash
 * @property string $chain_root_checksum
 * @property list<string> $finding_codes
 * @property string $disk
 * @property string|null $path
 * @property string $mime_type
 * @property int|null $size_bytes
 * @property string|null $artifact_checksum
 * @property string|null $signature_algorithm
 * @property string|null $signing_key_reference
 * @property string|null $signature
 * @property string|null $initiated_by
 * @property string $initiated_by_name
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property string $evidence_checksum
 */
#[Fillable(['environment', 'outcome', 'event_count', 'verified_event_count', 'legacy_event_count', 'mismatch_count', 'first_event_id', 'last_event_id', 'first_event_hash', 'last_event_hash', 'chain_root_checksum', 'finding_codes', 'disk', 'path', 'mime_type', 'size_bytes', 'artifact_checksum', 'signature_algorithm', 'signing_key_reference', 'signature', 'initiated_by', 'initiated_by_name', 'started_at', 'completed_at', 'evidence_checksum'])]
class AuditAssuranceRun extends Model
{
    /** @use HasFactory<AuditAssuranceRunFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['event_count' => 'integer', 'verified_event_count' => 'integer', 'legacy_event_count' => 'integer', 'mismatch_count' => 'integer', 'finding_codes' => 'array', 'size_bytes' => 'integer', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
