<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DataSubjectRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $assigned_to
 * @property string|null $identity_verified_by
 * @property string|null $decided_by
 * @property string $reference
 * @property string $request_type
 * @property string $requester_name
 * @property string $requester_contact
 * @property string $contact_channel
 * @property string $scope
 * @property string $identity_status
 * @property string|null $identity_evidence_reference
 * @property string $status
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision
 * @property string|null $decision_reason
 * @property string|null $response_evidence_reference
 * @property array<string, mixed>|null $metadata
 * @property User|null $assignee
 * @property User|null $identityVerifier
 * @property User|null $decisionMaker
 */
#[Fillable(['assigned_to', 'identity_verified_by', 'decided_by', 'reference', 'request_type', 'requester_name', 'requester_contact', 'contact_channel', 'scope', 'identity_status', 'identity_evidence_reference', 'status', 'received_at', 'due_at', 'acknowledged_at', 'decided_at', 'decision', 'decision_reason', 'response_evidence_reference', 'metadata'])]
class DataSubjectRequest extends Model
{
    /** @use HasFactory<DataSubjectRequestFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $hidden = ['requester_name', 'requester_contact'];

    protected $attributes = ['identity_status' => 'pending', 'status' => 'received'];

    protected function casts(): array
    {
        return ['requester_name' => 'encrypted', 'requester_contact' => 'encrypted', 'received_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function identityVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
