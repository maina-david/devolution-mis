<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference_data_release_id
 * @property string|null $service_desk_policy_id
 * @property string|null $service_desk_policy_checksum
 * @property string $requester_id
 * @property string|null $county_id
 * @property string|null $assigned_to
 * @property string|null $resolved_by
 * @property string|null $closed_by
 * @property string $reference
 * @property string $category
 * @property string $priority
 * @property string $channel
 * @property string $subject
 * @property string $description
 * @property string $status
 * @property string|null $resolution_summary
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $first_response_due_at
 * @property CarbonImmutable $resolution_due_at
 * @property CarbonImmutable|null $first_responded_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $escalated_at
 * @property User $requester
 * @property User|null $assignee
 * @property User|null $resolver
 * @property User|null $closer
 * @property County|null $county
 * @property ReferenceDataRelease $referenceDataRelease
 * @property ServiceDeskPolicy|null $serviceDeskPolicy
 * @property Collection<int, SupportTicketActivity> $activities
 * @property Collection<int, DocumentLink> $documentLinks
 * @property int $activities_count
 */
#[Fillable(['reference_data_release_id', 'service_desk_policy_id', 'service_desk_policy_checksum', 'requester_id', 'county_id', 'assigned_to', 'resolved_by', 'closed_by', 'reference', 'category', 'priority', 'channel', 'subject', 'description', 'status', 'resolution_summary', 'requested_at', 'first_response_due_at', 'resolution_due_at', 'first_responded_at', 'resolved_at', 'closed_at', 'last_activity_at', 'reminder_sent_at', 'escalated_at'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open', 'channel' => 'web'];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'resolution_summary' => 'encrypted',
            'requested_at' => 'immutable_datetime',
            'first_response_due_at' => 'immutable_datetime',
            'resolution_due_at' => 'immutable_datetime',
            'first_responded_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<ServiceDeskPolicy, $this> */
    public function serviceDeskPolicy(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskPolicy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<SupportTicketActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class)->orderBy('occurred_at');
    }

    /** @return MorphMany<DocumentLink, $this> */
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'subject');
    }
}
