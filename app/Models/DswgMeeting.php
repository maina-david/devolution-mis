<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DswgMeetingFactory;
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
 * @property string $reference
 * @property string $title
 * @property string $meeting_mode
 * @property string $status
 * @property string $agenda
 * @property string|null $minutes
 * @property int $quorum_required
 * @property string|null $minutes_recorded_by
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property-read DswgWorkingGroup $workingGroup
 */
#[Fillable(['dswg_working_group_id', 'dswg_meeting_series_id', 'occurrence_sequence', 'workflow_instance_id', 'reference', 'title', 'starts_at', 'ends_at', 'meeting_mode', 'venue', 'virtual_link', 'agenda', 'quorum_required', 'status', 'minutes', 'organized_by', 'minutes_recorded_by', 'minutes_recorded_at', 'minutes_approved_by', 'minutes_approved_at', 'reminder_sent_at'])]
class DswgMeeting extends Model
{
    /** @use HasFactory<DswgMeetingFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'scheduled'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'minutes_recorded_at' => 'immutable_datetime', 'minutes_approved_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DswgWorkingGroup, $this> */
    public function workingGroup(): BelongsTo
    {
        return $this->belongsTo(DswgWorkingGroup::class, 'dswg_working_group_id');
    }

    /** @return BelongsTo<DswgMeetingSeries, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DswgMeetingSeries::class, 'dswg_meeting_series_id');
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function invitees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dswg_meeting_user')->withPivot(['invitation_status', 'attendance_status', 'meeting_role', 'invited_at', 'responded_at'])->withTimestamps();
    }

    /** @return HasMany<DswgDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(DswgDecision::class);
    }

    /** @return HasMany<DswgAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(DswgAction::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
