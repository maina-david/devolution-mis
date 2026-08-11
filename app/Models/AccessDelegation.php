<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AccessDelegationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $requested_by
 * @property string $beneficiary_id
 * @property string|null $reference_data_release_id
 * @property string|null $approved_by
 * @property string|null $revoked_by
 * @property string|null $reviewed_by
 * @property string $reference
 * @property string $access_type
 * @property string $scope_type
 * @property list<string> $permission_scope
 * @property list<array<string, mixed>> $county_scope_snapshot
 * @property string $business_justification
 * @property string|null $incident_reference
 * @property string|null $compensating_controls
 * @property string $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $decision_rationale
 * @property string|null $revocation_reason
 * @property string|null $post_use_outcome
 * @property string|null $post_use_findings
 * @property string|null $approval_checksum
 * @property User $requester
 * @property User $beneficiary
 * @property User|null $approver
 * @property User|null $revoker
 * @property User|null $reviewer
 * @property ReferenceDataRelease|null $referenceDataRelease
 */
#[Fillable(['requested_by', 'beneficiary_id', 'reference_data_release_id', 'approved_by', 'revoked_by', 'reviewed_by', 'reference', 'access_type', 'scope_type', 'permission_scope', 'county_scope_snapshot', 'business_justification', 'incident_reference', 'compensating_controls', 'status', 'starts_at', 'expires_at', 'approved_at', 'activated_at', 'expired_at', 'revoked_at', 'reviewed_at', 'decision_rationale', 'revocation_reason', 'post_use_outcome', 'post_use_findings', 'approval_checksum'])]
class AccessDelegation extends Model
{
    /** @use HasFactory<AccessDelegationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['permission_scope' => 'array', 'county_scope_snapshot' => 'array', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'activated_at' => 'immutable_datetime', 'expired_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
