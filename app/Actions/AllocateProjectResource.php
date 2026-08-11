<?php

namespace App\Actions;

use App\Models\DevolutionProject;
use App\Models\ProjectResource;
use App\Models\ProjectResourceAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateProjectResource
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionProject $project, User $actor, array $attributes): ProjectResourceAllocation
    {
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, 'Project resource planning is locked after closure.');

        return DB::transaction(function () use ($project, $actor, $attributes): ProjectResourceAllocation {
            $resource = ProjectResource::query()->lockForUpdate()->findOrFail((string) $attributes['project_resource_id']);
            $milestone = $project->milestones()->findOrFail((string) $attributes['project_milestone_id']);
            abort_unless($resource->devolution_project_id === $project->id, 404);
            abort_if($resource->status !== 'active', 409, 'Only active resources may be allocated.');

            $startsOn = CarbonImmutable::parse((string) $attributes['starts_on'])->startOfDay();
            $endsOn = CarbonImmutable::parse((string) $attributes['ends_on'])->startOfDay();
            if ($startsOn->lessThan($resource->available_from) || $endsOn->greaterThan($resource->available_to)) {
                throw ValidationException::withMessages(['starts_on' => 'The allocation must be within the resource availability period.']);
            }
            if ($startsOn->lessThan(CarbonImmutable::parse($milestone->planned_start_date)) || $endsOn->greaterThan(CarbonImmutable::parse($milestone->planned_end_date))) {
                throw ValidationException::withMessages(['starts_on' => 'The allocation must be within the selected milestone period.']);
            }

            $overlaps = $resource->allocations()->whereDate('starts_on', '<=', $endsOn)->whereDate('ends_on', '>=', $startsOn)->get();
            $requestedRate = (float) $attributes['planned_units_per_day'];
            for ($date = $startsOn; $date->lessThanOrEqualTo($endsOn); $date = $date->addDay()) {
                $allocatedRate = $overlaps->sum(fn (ProjectResourceAllocation $allocation): float => $date->betweenIncluded($allocation->starts_on, $allocation->ends_on) ? (float) $allocation->planned_units_per_day : 0.0);
                if ($allocatedRate + $requestedRate > (float) $resource->capacity_per_day + 0.00001) {
                    throw ValidationException::withMessages(['planned_units_per_day' => "Resource capacity is exceeded on {$date->toDateString()}."]);
                }
            }

            $days = (int) $startsOn->diffInDays($endsOn) + 1;
            $plannedUnits = round($days * $requestedRate, 4);
            $plannedCost = round($plannedUnits * (float) $resource->cost_rate, 2);
            $payload = [
                'project_resource_id' => $resource->id,
                'project_milestone_id' => $milestone->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'planned_units_per_day' => $requestedRate,
                'planned_units' => $plannedUnits,
                'planned_cost' => $plannedCost,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
            ];
            $allocation = $resource->allocations()->create([
                ...$payload,
                'allocation_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ]);
            $this->auditLogger->record($actor, $allocation, 'project.resource_allocated', "Resource {$resource->code} allocated to milestone {$milestone->code}.", $project->lead_county_id, [
                'planned_units' => $allocation->planned_units,
                'planned_cost' => $allocation->planned_cost,
                'allocation_checksum' => $allocation->allocation_checksum,
            ]);

            return $allocation;
        }, attempts: 3);
    }
}
