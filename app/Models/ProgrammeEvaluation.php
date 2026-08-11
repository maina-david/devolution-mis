<?php

namespace App\Models;

use Database\Factories\ProgrammeEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $title
 * @property string $evaluation_type
 * @property string $status
 * @property string|null $county_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
#[Fillable(['workflow_instance_id', 'programme_id', 'county_id', 'code', 'title', 'evaluation_type', 'period_start', 'period_end', 'status', 'terms_of_reference', 'methodology', 'executive_summary', 'findings', 'recommendations', 'lead_evaluator_id', 'created_by', 'reference_data_release_id', 'approved_by', 'approved_at'])]
class ProgrammeEvaluation extends Model
{
    /** @use HasFactory<ProgrammeEvaluationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'findings' => 'array', 'recommendations' => 'array', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
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

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }

    /** @return HasMany<EvaluationFinding, $this> */
    public function governedFindings(): HasMany
    {
        return $this->hasMany(EvaluationFinding::class);
    }
}
