<?php

namespace App\Models;

use Database\Factories\LearningCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $maximum_attempts
 * @property string $passing_score
 * @property string|null $reference_data_release_id
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 */
#[Fillable(['workflow_instance_id', 'reference_data_release_id', 'sector_id', 'county_id', 'owner_id', 'code', 'slug', 'title', 'summary', 'description', 'category', 'level', 'delivery_mode', 'language', 'estimated_minutes', 'passing_score', 'maximum_attempts', 'status', 'published_at', 'retired_at', 'created_by'])]
class LearningCourse extends Model
{
    /** @use HasFactory<LearningCourseFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['passing_score' => 'decimal:2', 'published_at' => 'immutable_datetime', 'retired_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
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

    /** @return HasMany<LearningModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(LearningModule::class);
    }

    /** @return HasMany<LearningEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningEnrollment::class);
    }

    /** @return HasMany<VirtualClassroom, $this> */
    public function classrooms(): HasMany
    {
        return $this->hasMany(VirtualClassroom::class);
    }

    /** @return HasMany<LearningCohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(LearningCohort::class);
    }

    /** @return BelongsToMany<KnowledgeItem, $this> */
    public function knowledgeItems(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeItem::class)->withTimestamps();
    }

    /** @return HasMany<LearningOfflinePackage, $this> */
    public function offlinePackages(): HasMany
    {
        return $this->hasMany(LearningOfflinePackage::class);
    }

    /** @return HasOne<LearningOfflinePackage, $this> */
    public function latestOfflinePackage(): HasOne
    {
        return $this->hasOne(LearningOfflinePackage::class)->orderByDesc('package_version');
    }

    /** @return HasOne<LearningOfflinePackage, $this> */
    public function latestReadyOfflinePackage(): HasOne
    {
        return $this->hasOne(LearningOfflinePackage::class)->where('status', 'ready')->orderByDesc('package_version');
    }
}
