<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceDeskPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property int $version
 * @property string $name
 * @property string $description
 * @property string $business_calendar_id
 * @property string $authority_status
 * @property string|null $approval_reference
 * @property list<array<string, mixed>> $categories
 * @property list<string> $channels
 * @property array<string, array<string, mixed>> $priority_targets
 * @property list<array<string, mixed>> $escalation_rules
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $status
 * @property string $created_by
 * @property string|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property string|null $checksum
 * @property BusinessCalendar $businessCalendar
 * @property Collection<int, ServiceDeskRosterMember> $rosterMembers
 */
#[Fillable(['code', 'version', 'name', 'description', 'business_calendar_id', 'authority_status', 'approval_reference', 'categories', 'channels', 'priority_targets', 'escalation_rules', 'effective_from', 'effective_to', 'status', 'created_by', 'published_by', 'published_at', 'checksum'])]
class ServiceDeskPolicy extends Model
{
    /** @use HasFactory<ServiceDeskPolicyFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'authority_status' => 'provisional'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'categories' => 'array',
            'channels' => 'array',
            'priority_targets' => 'array',
            'escalation_rules' => 'array',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function businessCalendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class);
    }

    /** @return HasMany<ServiceDeskRosterMember, $this> */
    public function rosterMembers(): HasMany
    {
        return $this->hasMany(ServiceDeskRosterMember::class)->orderBy('tier')->orderByDesc('is_primary');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return array<string, mixed> */
    public function canonicalPayload(): array
    {
        $this->loadMissing(['businessCalendar:id,code,version,checksum', 'rosterMembers.user:id,name', 'rosterMembers.county:id,code,name']);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'business_calendar' => [
                'id' => $this->businessCalendar->id,
                'code' => $this->businessCalendar->code,
                'version' => $this->businessCalendar->version,
                'checksum' => $this->businessCalendar->checksum,
            ],
            'authority_status' => $this->authority_status,
            'approval_reference' => $this->approval_reference,
            'categories' => $this->categories,
            'channels' => $this->channels,
            'priority_targets' => $this->priority_targets,
            'escalation_rules' => $this->escalation_rules,
            'effective_from' => $this->effective_from->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
            'roster' => $this->rosterMembers->sortBy('id')->map(fn (ServiceDeskRosterMember $member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'user_name' => $member->user->name,
                'county_id' => $member->county_id,
                'county_code' => $member->county?->code,
                'tier' => $member->tier,
                'duty_role' => $member->duty_role,
                'is_primary' => $member->is_primary,
                'starts_at' => $member->starts_at->toIso8601String(),
                'ends_at' => $member->ends_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
