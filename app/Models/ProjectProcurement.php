<?php

namespace App\Models;

use Database\Factories\ProjectProcurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $reference */
#[Fillable(['devolution_project_id', 'reference', 'title', 'method', 'status', 'estimated_value', 'contract_value', 'currency', 'planned_notice_date', 'award_date', 'supplier_name', 'contract_reference'])]
class ProjectProcurement extends Model
{
    /** @use HasFactory<ProjectProcurementFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'contract_value' => 'decimal:2', 'planned_notice_date' => 'date', 'award_date' => 'date'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }
}
