<?php

namespace App\Models;

use Database\Factories\ProjectBudgetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $code */
#[Fillable(['devolution_project_id', 'code', 'category', 'description', 'approved_amount', 'committed_amount', 'actual_amount', 'currency', 'financial_year', 'funding_source'])]
class ProjectBudgetLine extends Model
{
    /** @use HasFactory<ProjectBudgetLineFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['approved_amount' => 'decimal:2', 'committed_amount' => 'decimal:2', 'actual_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }
}
