<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDevolutionProject
{
    public function __construct(
        private StartWorkflow $startWorkflow,
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): DevolutionProject
    {
        $countyIds = array_values(array_unique($attributes['county_ids']));
        if (! in_array($attributes['lead_county_id'], $countyIds, true)) {
            throw ValidationException::withMessages(['lead_county_id' => 'The lead county must be included in the participating counties.']);
        }
        $counties = County::query()->whereKey($countyIds)->get();
        if ($counties->count() !== count($countyIds) || $counties->contains(fn (County $county): bool => ! $actor->canAccessCounty($county))) {
            abort(403, 'One or more selected counties are outside your authorized scope.');
        }
        $definition = WorkflowDefinition::query()->where('code', 'PROJECT-LIFECYCLE')->where('status', 'active')->firstOrFail();

        return DB::transaction(function () use ($actor, $attributes, $countyIds, $definition): DevolutionProject {
            $referenceDataRelease = $this->referenceDataReleaseResolver->forProject($attributes, $countyIds, now());
            $project = DevolutionProject::query()->create([
                ...Arr::except($attributes, ['county_ids', 'indicator_ids']),
                'created_by' => $actor->id,
                'reference_data_release_id' => $referenceDataRelease->id,
            ]);
            $project->counties()->attach(collect($countyIds)->mapWithKeys(fn (string $countyId): array => [$countyId => ['is_lead' => $countyId === $project->lead_county_id]])->all());
            $project->indicators()->sync($attributes['indicator_ids'] ?? []);
            $workflow = $this->startWorkflow->handle($definition, $project, $actor, ['approved_budget' => $attributes['approved_budget'], 'county_count' => count($countyIds)], $project->lead_county_id);
            $project->update(['workflow_instance_id' => $workflow->id, 'lifecycle_stage' => $workflow->current_state]);
            $this->auditLogger->record($actor, $project, 'project.created', "Project {$project->code} initiated.", $project->lead_county_id, [
                'counties' => $countyIds,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $project->refresh();
        });
    }
}
