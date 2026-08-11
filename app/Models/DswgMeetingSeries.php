<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DswgMeetingSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference_prefix
 * @property string $title
 * @property string $frequency
 * @property int $interval
 * @property CarbonImmutable $next_occurrence_at
 * @property CarbonImmutable $ends_on
 * @property int $duration_minutes
 * @property string $timezone
 * @property int $quorum_required
 * @property int $generation_horizon_days
 * @property int $next_sequence
 * @property string $status
 * @property-read DswgWorkingGroup $workingGroup
 */
#[Fillable(['dswg_working_group_id', 'reference_prefix', 'title', 'frequency', 'interval', 'next_occurrence_at', 'ends_on', 'duration_minutes', 'timezone', 'meeting_mode', 'venue', 'virtual_link', 'agenda', 'quorum_required', 'generation_horizon_days', 'next_sequence', 'status', 'created_by'])]
class DswgMeetingSeries extends Model
{
    /** @use HasFactory<DswgMeetingSeriesFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['interval' => 1, 'generation_horizon_days' => 90, 'next_sequence' => 1, 'status' => 'active'];

    protected function casts(): array
    {
        return ['next_occurrence_at' => 'immutable_datetime', 'ends_on' => 'immutable_date'];
    }

    /** @return BelongsTo<DswgWorkingGroup, $this> */
    public function workingGroup(): BelongsTo
    {
        return $this->belongsTo(DswgWorkingGroup::class, 'dswg_working_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function invitees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dswg_meeting_series_user')->withTimestamps();
    }

    /** @return HasMany<DswgMeeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(DswgMeeting::class);
    }
}
