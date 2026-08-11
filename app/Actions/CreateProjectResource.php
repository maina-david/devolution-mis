<?php

namespace App\Actions;

use App\Models\DevolutionProject;
use App\Models\ProjectResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateProjectResource
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionProject $project, User $actor, array $attributes): ProjectResource
    {
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, 'Project resource planning is locked after closure.');

        return DB::transaction(function () use ($project, $actor, $attributes): ProjectResource {
            $resource = $project->resources()->create([
                ...$attributes,
                'currency' => $project->currency,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
            $this->auditLogger->record($actor, $resource, 'project.resource_created', "Project resource {$resource->code} created.", $project->lead_county_id, [
                'capacity_per_day' => $resource->capacity_per_day,
                'capacity_unit' => $resource->capacity_unit,
                'cost_rate' => $resource->cost_rate,
                'currency' => $resource->currency,
            ]);

            return $resource;
        });
    }
}
