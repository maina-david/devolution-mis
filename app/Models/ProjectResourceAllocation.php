<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectResourceAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $project_resource_id
 * @property string $project_milestone_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property string $planned_units_per_day
 * @property string $planned_units
 * @property string $planned_cost
 * @property string|null $notes
 * @property string $allocation_checksum
 */
#[Fillable(['project_resource_id', 'project_milestone_id', 'starts_on', 'ends_on', 'planned_units_per_day', 'planned_units', 'planned_cost', 'notes', 'allocation_checksum', 'created_by'])]
class ProjectResourceAllocation extends Model
{
    /** @use HasFactory<ProjectResourceAllocationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'planned_units_per_day' => 'decimal:4', 'planned_units' => 'decimal:4', 'planned_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<ProjectResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ProjectResource::class, 'project_resource_id');
    }

    /** @return BelongsTo<ProjectMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'project_milestone_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
