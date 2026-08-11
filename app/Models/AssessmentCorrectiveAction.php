<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AssessmentCorrectiveActionFactory;
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
 * @property string $assessment_corrective_plan_id
 * @property string $accountable_owner_id
 * @property string $code
 * @property string $title
 * @property string $description
 * @property string $success_indicator
 * @property string $target
 * @property CarbonImmutable $due_at
 * @property string $progress_percentage
 * @property string $status
 * @property-read AssessmentCorrectivePlan $plan
 * @property-read User $owner
 * @property-read Collection<int, AssessmentCorrectiveUpdate> $updates
 */
#[Fillable(['assessment_corrective_plan_id', 'accountable_owner_id', 'code', 'title', 'description', 'success_indicator', 'target', 'due_at', 'progress_percentage', 'status'])]
class AssessmentCorrectiveAction extends Model
{
    /** @use HasFactory<AssessmentCorrectiveActionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['due_at' => 'date', 'progress_percentage' => 'decimal:2'];
    }

    /** @return BelongsTo<AssessmentCorrectivePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssessmentCorrectivePlan::class, 'assessment_corrective_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_owner_id');
    }

    /** @return HasMany<AssessmentCorrectiveUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(AssessmentCorrectiveUpdate::class);
    }
}
