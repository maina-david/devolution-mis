<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $devolution_project_id
 * @property string $code
 * @property string $title
 * @property CarbonInterface $planned_start_date
 * @property CarbonInterface $planned_end_date
 * @property CarbonInterface|null $actual_start_date
 * @property CarbonInterface|null $actual_end_date
 * @property string $weight
 * @property string $progress
 * @property string $status
 * @property list<string>|null $dependencies
 */
#[Fillable(['devolution_project_id', 'code', 'title', 'description', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date', 'weight', 'progress', 'status', 'owner_id', 'dependencies'])]
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['planned_start_date' => 'date', 'planned_end_date' => 'date', 'actual_start_date' => 'date', 'actual_end_date' => 'date', 'weight' => 'decimal:2', 'progress' => 'decimal:2', 'dependencies' => 'array'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }

    /** @return HasMany<ProjectResourceAllocation, $this> */
    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ProjectResourceAllocation::class);
    }
}
