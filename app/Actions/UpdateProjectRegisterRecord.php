<?php

namespace App\Actions;

use App\Models\DevolutionProject;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectMilestone;
use App\Models\ProjectProcurement;
use App\Models\ProjectRisk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProjectDependencyGraph;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateProjectRegisterRecord
{
    public function __construct(private AuditLogger $auditLogger, private ProjectDependencyGraph $dependencyGraph) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(DevolutionProject $project, ProjectMilestone|ProjectBudgetLine|ProjectRisk|ProjectProcurement $record, User $actor, array $attributes): Model
    {
        abort_unless($record->devolution_project_id === $project->id, 404);
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, __('projects.errors.control_register_locked'));

        return DB::transaction(function () use ($project, $record, $actor, $attributes): Model {
            $reason = (string) $attributes['amendment_reason'];
            $changes = Arr::except($attributes, ['amendment_reason']);
            if ($record instanceof ProjectMilestone) {
                $otherWeight = (float) $project->milestones()->whereKeyNot($record->id)->sum('weight');
                abort_if($otherWeight + (float) $changes['weight'] > 100, 422, __('projects.errors.milestone_weight_limit'));
                /** @var list<string> $dependencyIds */
                $dependencyIds = $changes['dependencies'] ?? [];
                $this->dependencyGraph->validate($project, $record, $dependencyIds);
            }

            $before = Arr::only($record->getAttributes(), array_keys($changes));
            $record->update($changes);
            if ($record instanceof ProjectBudgetLine) {
                $project->update([
                    'committed_amount' => $project->budgetLines()->sum('committed_amount'),
                    'actual_expenditure' => $project->budgetLines()->sum('actual_amount'),
                ]);
            }
            $after = Arr::only($record->refresh()->getAttributes(), array_keys($changes));
            $register = match (true) {
                $record instanceof ProjectMilestone => 'milestone',
                $record instanceof ProjectBudgetLine => 'budget_line',
                $record instanceof ProjectRisk => 'risk',
                $record instanceof ProjectProcurement => 'procurement',
            };
            $this->auditLogger->record($actor, $record, "project.{$register}_amended", __('projects.audit.register_amended', ['register' => __('projects.registers.'.$register)]), $project->lead_county_id, ['reason' => $reason, 'before' => $before, 'after' => $after]);

            return $record;
        });
    }
}
