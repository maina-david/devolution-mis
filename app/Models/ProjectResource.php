<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectResourceFactory;
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
 * @property string $name
 * @property string $resource_type
 * @property string $capacity_unit
 * @property string $capacity_per_day
 * @property string $cost_rate
 * @property string $currency
 * @property CarbonImmutable $available_from
 * @property CarbonImmutable $available_to
 * @property string $status
 */
#[Fillable(['devolution_project_id', 'code', 'name', 'resource_type', 'capacity_unit', 'capacity_per_day', 'cost_rate', 'currency', 'available_from', 'available_to', 'status', 'created_by'])]
class ProjectResource extends Model
{
    /** @use HasFactory<ProjectResourceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['capacity_per_day' => 'decimal:4', 'cost_rate' => 'decimal:2', 'available_from' => 'immutable_date', 'available_to' => 'immutable_date'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }

    /** @return HasMany<ProjectResourceAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ProjectResourceAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
