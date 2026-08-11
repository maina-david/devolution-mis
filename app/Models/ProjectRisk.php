<?php

namespace App\Models;

use Database\Factories\ProjectRiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $code @property int $probability @property int $impact */
#[Fillable(['devolution_project_id', 'code', 'category', 'description', 'probability', 'impact', 'residual_probability', 'residual_impact', 'mitigation', 'status', 'owner_id', 'review_due_date'])]
class ProjectRisk extends Model
{
    /** @use HasFactory<ProjectRiskFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['review_due_date' => 'date'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }
}
