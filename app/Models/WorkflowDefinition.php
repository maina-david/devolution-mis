<?php

namespace App\Models;

use Database\Factories\WorkflowDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $module
 * @property string|null $description
 * @property string $status
 * @property int $active_instances_count
 * @property int $overdue_instances_count
 */
#[Fillable(['code', 'name', 'module', 'description', 'status'])]
class WorkflowDefinition extends Model
{
    /** @use HasFactory<WorkflowDefinitionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    /** @return HasMany<WorkflowVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    /** @return HasManyThrough<WorkflowInstance, WorkflowVersion, $this> */
    public function instances(): HasManyThrough
    {
        return $this->hasManyThrough(WorkflowInstance::class, WorkflowVersion::class);
    }
}
