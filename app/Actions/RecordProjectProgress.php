<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\IndicatorDefinition;
use App\Models\ProjectProgressUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordProjectProgress
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionProject $project, User $actor, array $attributes): ProjectProgressUpdate
    {
        $countyIds = array_values($project->counties()->get(['counties.id'])->filter(fn (County $county): bool => $actor->canAccessCounty($county))->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all());
        abort_unless($countyIds !== [], 403);
        /** @var list<array<string, mixed>> $results */
        $results = $attributes['indicator_results'] ?? [];
        $this->validateResults($project, $countyIds, $results);

        $update = DB::transaction(function () use ($project, $actor, $attributes, $results): ProjectProgressUpdate {
            $update = $project->progressUpdates()->create([...Arr::except($attributes, ['indicator_results']), 'submitted_by' => $actor->id, 'verification_status' => 'submitted']);
            $update->indicatorResults()->createMany($results);

            return $update;
        });
        $this->auditLogger->record($actor, $update, 'project.progress_submitted', __('projects.audit.progress_submitted', ['code' => $project->code]), $project->lead_county_id, ['reporting_date' => $attributes['reporting_date'], 'provenance' => $attributes['provenance']]);

        return $update;
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<array<string, mixed>>  $results
     */
    private function validateResults(DevolutionProject $project, array $countyIds, array $results): void
    {
        if ($results !== [] && $project->programme_id === null) {
            throw ValidationException::withMessages(['indicator_results' => __('projects.errors.programme_required_for_results')]);
        }
        $seen = [];
        foreach ($results as $index => $result) {
            $indicator = IndicatorDefinition::query()->find($result['indicator_definition_id']);
            $countyId = (string) $result['county_id'];
            $key = implode('|', [$result['indicator_definition_id'], $countyId, $result['dimension_key']]);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["indicator_results.{$index}.dimension_key" => __('projects.errors.duplicate_indicator_dimension')]);
            }
            $seen[$key] = true;

            if (! $indicator instanceof IndicatorDefinition || ! $indicator->isCurrentApprovedVersion() || ! $project->indicators()->whereKey($indicator->id)->exists()) {
                throw ValidationException::withMessages(["indicator_results.{$index}.indicator_definition_id" => __('projects.errors.approved_linked_indicator')]);
            }
            if (! in_array($countyId, $countyIds, true)) {
                throw ValidationException::withMessages(["indicator_results.{$index}.county_id" => __('projects.errors.authorized_result_county')]);
            }
            $isNarrative = $indicator->value_type === 'text';
            if (($isNarrative && blank($result['narrative_value'] ?? null)) || (! $isNarrative && ! isset($result['numeric_value']))) {
                throw ValidationException::withMessages(["indicator_results.{$index}.".($isNarrative ? 'narrative_value' : 'numeric_value') => __('projects.errors.indicator_value_type')]);
            }
        }
    }
}
