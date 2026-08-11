<?php

namespace App\Models;

use Database\Factories\IgrResolutionDependencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['dependent_resolution_id', 'prerequisite_resolution_id', 'dependency_type', 'rationale', 'created_by'])]
class IgrResolutionDependency extends Model
{
    /** @use HasFactory<IgrResolutionDependencyFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<IgrResolution, $this> */
    public function dependentResolution(): BelongsTo
    {
        return $this->belongsTo(IgrResolution::class, 'dependent_resolution_id');
    }

    /** @return BelongsTo<IgrResolution, $this> */
    public function prerequisiteResolution(): BelongsTo
    {
        return $this->belongsTo(IgrResolution::class, 'prerequisite_resolution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
