<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DswgActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $dswg_meeting_id
 * @property string $code
 * @property string $title
 * @property string $status
 * @property string $priority
 * @property int $progress_percentage
 * @property string $accountable_user_id
 * @property string|null $accountable_organization_id
 * @property string|null $reference_data_release_id
 * @property string|null $county_id
 * @property string|null $progress_note
 * @property string|null $completion_evidence
 * @property CarbonImmutable $due_on
 * @property-read DswgMeeting $meeting
 * @property-read User $accountableUser
 * @property-read Organization|null $accountableOrganization
 * @property-read County|null $county
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 */
#[Fillable(['dswg_meeting_id', 'dswg_decision_id', 'workflow_instance_id', 'code', 'title', 'description', 'accountable_user_id', 'accountable_organization_id', 'reference_data_release_id', 'county_id', 'due_on', 'priority', 'status', 'progress_percentage', 'progress_note', 'completion_evidence', 'created_by', 'completed_by', 'completed_at', 'verified_by', 'verified_at', 'reminder_sent_at'])]
class DswgAction extends Model
{
    /** @use HasFactory<DswgActionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['priority' => 'medium', 'status' => 'open', 'progress_percentage' => 0];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DswgMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(DswgMeeting::class, 'dswg_meeting_id');
    }

    /** @return BelongsTo<DswgDecision, $this> */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(DswgDecision::class, 'dswg_decision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function accountableUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function accountableOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'accountable_organization_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return MorphOne<WorkflowInstance, $this> */
    public function lifecycle(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
