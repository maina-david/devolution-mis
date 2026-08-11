<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IgrResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $igr_forum_id
 * @property string|null $workflow_instance_id
 * @property string $resolution_number
 * @property string $title
 * @property string $resolution_text
 * @property CarbonImmutable $resolved_on
 * @property CarbonImmutable $due_on
 * @property string $priority
 * @property string $status
 * @property int $progress_percentage
 * @property string|null $implementation_gap
 * @property string|null $closure_evidence
 * @property string|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property-read IgrForum $forum
 * @property-read WorkflowInstance|null $workflowInstance
 */
#[Fillable(['igr_forum_id', 'igr_forum_meeting_id', 'workflow_instance_id', 'resolution_number', 'title', 'resolution_text', 'resolved_on', 'due_on', 'priority', 'status', 'progress_percentage', 'implementation_gap', 'closure_evidence', 'created_by', 'reference_data_release_id', 'closed_by', 'closed_at', 'reminder_sent_at'])]
class IgrResolution extends Model
{
    /** @use HasFactory<IgrResolutionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['resolved_on' => 'immutable_date', 'due_on' => 'immutable_date', 'closed_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime', 'progress_percentage' => 'integer'];
    }

    /** @return BelongsTo<IgrForum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(IgrForum::class, 'igr_forum_id');
    }

    /** @return BelongsTo<IgrForumMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(IgrForumMeeting::class, 'igr_forum_meeting_id');
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

    /** @return HasMany<IgrResolutionAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(IgrResolutionAssignment::class);
    }

    /** @return HasMany<IgrResolutionUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(IgrResolutionUpdate::class);
    }

    /** @return HasMany<IgrResolutionDependency, $this> */
    public function dependencies(): HasMany
    {
        return $this->hasMany(IgrResolutionDependency::class, 'dependent_resolution_id');
    }

    /** @return HasMany<IgrResolutionDependency, $this> */
    public function dependents(): HasMany
    {
        return $this->hasMany(IgrResolutionDependency::class, 'prerequisite_resolution_id');
    }

    /** @return HasMany<IgrResolutionGap, $this> */
    public function gaps(): HasMany
    {
        return $this->hasMany(IgrResolutionGap::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')
            ->where('subject_type', $this->getMorphClass());
    }
}
