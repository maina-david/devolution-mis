<?php

namespace App\Models;

use Database\Factories\DevolutionProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $title
 * @property string $lead_county_id
 * @property string|null $programme_id
 * @property string|null $reference_data_release_id
 * @property string $lifecycle_stage
 * @property string $status
 * @property string $approved_budget
 * @property string $actual_expenditure
 * @property string $currency
 * @property string $physical_progress
 * @property int $milestones_count
 * @property int $risks_count
 * @property-read WorkflowInstance|null $workflowInstance
 */
#[Fillable(['code', 'title', 'description', 'programme_id', 'sector_id', 'lead_county_id', 'funding_organization_id', 'project_manager_id', 'workflow_instance_id', 'reference_data_release_id', 'lifecycle_stage', 'status', 'planned_start_date', 'planned_end_date', 'actual_start_date', 'actual_end_date', 'approved_budget', 'committed_amount', 'actual_expenditure', 'currency', 'physical_progress', 'investment_registry_reference', 'funding_source', 'location', 'climate_risk_screening', 'created_by'])]
class DevolutionProject extends Model
{
    /** @use HasFactory<DevolutionProjectFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['planned_start_date' => 'date', 'planned_end_date' => 'date', 'actual_start_date' => 'date', 'actual_end_date' => 'date', 'approved_budget' => 'decimal:2', 'committed_amount' => 'decimal:2', 'actual_expenditure' => 'decimal:2', 'physical_progress' => 'decimal:2', 'location' => 'array', 'climate_risk_screening' => 'array'];
    }

    /** @return BelongsTo<County, $this> */
    public function leadCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'lead_county_id');
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsToMany<County, $this> */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'devolution_project_county')->withPivot('is_lead')->withTimestamps();
    }

    /** @return BelongsToMany<IndicatorDefinition, $this> */
    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(IndicatorDefinition::class, 'devolution_project_indicator')->withPivot('is_primary')->withTimestamps();
    }

    /** @return HasMany<ProjectMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    /** @return HasMany<ProjectScheduleBaseline, $this> */
    public function scheduleBaselines(): HasMany
    {
        return $this->hasMany(ProjectScheduleBaseline::class);
    }

    /** @return HasMany<ProjectScheduleBaseline, $this> */
    public function approvedScheduleBaselines(): HasMany
    {
        return $this->scheduleBaselines()->where('status', 'approved');
    }

    /** @return HasMany<ProjectBudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(ProjectBudgetLine::class);
    }

    /** @return HasMany<ProjectRisk, $this> */
    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    /** @return HasMany<ProjectProcurement, $this> */
    public function procurements(): HasMany
    {
        return $this->hasMany(ProjectProcurement::class);
    }

    /** @return HasMany<ProjectProgressUpdate, $this> */
    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProjectProgressUpdate::class);
    }

    /** @return HasMany<ProjectResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }
}
