<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IdentityLifecycleRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $source_system
 * @property string $source_event_id
 * @property string $source_evidence_reference
 * @property string $source_checksum
 * @property string $event_type
 * @property string $user_id
 * @property CarbonImmutable $effective_at
 * @property array{role:string|null, home_county_id:string|null, assigned_county_ids:list<string>, delegated_access_ids:list<string>, access_revoked_at:string|null} $current_access_snapshot
 * @property string|null $proposed_role
 * @property string|null $proposed_home_county_id
 * @property list<string> $proposed_assigned_county_ids
 * @property string $business_reason
 * @property string $status
 * @property string $requested_by
 * @property string|null $decided_by
 * @property string|null $decision_rationale
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $applied_at
 * @property string|null $applied_by
 * @property int $application_attempts
 * @property CarbonImmutable|null $last_application_attempt_at
 * @property string|null $application_error_code
 * @property int $sessions_revoked
 * @property string|null $evidence_checksum
 * @property User $user
 * @property User $requester
 * @property User|null $decider
 * @property User|null $applier
 * @property County|null $proposedHomeCounty
 */
#[Fillable(['source_system', 'source_event_id', 'source_evidence_reference', 'source_checksum', 'event_type', 'user_id', 'effective_at', 'current_access_snapshot', 'proposed_role', 'proposed_home_county_id', 'proposed_assigned_county_ids', 'business_reason', 'status', 'requested_by', 'decided_by', 'decision_rationale', 'decided_at', 'applied_at', 'applied_by', 'application_attempts', 'last_application_attempt_at', 'application_error_code', 'sessions_revoked', 'evidence_checksum'])]
class IdentityLifecycleRequest extends Model
{
    /** @use HasFactory<IdentityLifecycleRequestFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending', 'application_attempts' => 0, 'sessions_revoked' => 0];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'current_access_snapshot' => 'array', 'proposed_assigned_county_ids' => 'array', 'decided_at' => 'immutable_datetime', 'applied_at' => 'immutable_datetime', 'application_attempts' => 'integer', 'last_application_attempt_at' => 'immutable_datetime', 'sessions_revoked' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<County, $this> */
    public function proposedHomeCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'proposed_home_county_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
