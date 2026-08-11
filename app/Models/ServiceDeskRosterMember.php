<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceDeskRosterMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $service_desk_policy_id
 * @property string $user_id
 * @property string|null $county_id
 * @property int $tier
 * @property string $duty_role
 * @property bool $is_primary
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string $created_by
 * @property User $user
 * @property County|null $county
 */
#[Fillable(['service_desk_policy_id', 'user_id', 'county_id', 'tier', 'duty_role', 'is_primary', 'starts_at', 'ends_at', 'created_by'])]
class ServiceDeskRosterMember extends Model
{
    /** @use HasFactory<ServiceDeskRosterMemberFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['tier' => 'integer', 'is_primary' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ServiceDeskPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskPolicy::class, 'service_desk_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
