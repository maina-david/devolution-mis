<?php

namespace App\Models;

use Database\Factories\KnowledgeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $published_on
 * @property Carbon|null $review_due_at
 * @property list<string>|null $tags
 * @property string|null $reference_data_release_id
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 */
#[Fillable(['workflow_instance_id', 'reference_data_release_id', 'assessment_document_id', 'county_id', 'sector_id', 'author_id', 'reference', 'item_type', 'title', 'summary', 'content_body', 'tags', 'visibility', 'status', 'published_on', 'review_due_at', 'source_organization', 'external_url', 'language', 'metadata'])]
class KnowledgeItem extends Model
{
    /** @use HasFactory<KnowledgeItemFactory> */
    use HasFactory,HasUuids,SoftDeletes;

    protected function casts(): array
    {
        return ['tags' => 'array', 'metadata' => 'array', 'published_on' => 'immutable_date', 'review_due_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<LearningCourse, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(LearningCourse::class)->withTimestamps();
    }

    /** @return HasMany<KnowledgeDiscussion, $this> */
    public function discussions(): HasMany
    {
        return $this->hasMany(KnowledgeDiscussion::class);
    }
}
