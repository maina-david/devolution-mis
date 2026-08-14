<?php

namespace App\Actions;

use App\Models\IndicatorObservation;
use App\Models\ProjectProgressUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class IngestVerifiedProjectResults
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return Collection<int, IndicatorObservation> */
    public function handle(ProjectProgressUpdate $update, User $verificationActor): Collection
    {
        if ($update->verification_status !== 'verified') {
            throw ValidationException::withMessages(['status' => __('projects.errors.verified_progress_required')]);
        }

        $update->loadMissing(['project:id,code,programme_id', 'indicatorResults.indicator:id,code,value_type']);
        $observations = new Collection;
        foreach ($update->indicatorResults as $result) {
            $observation = IndicatorObservation::query()->firstOrCreate(
                ['source_project_indicator_result_id' => $result->id],
                [
                    'indicator_definition_id' => $result->indicator_definition_id,
                    'county_id' => $result->county_id,
                    'programme_id' => $update->project->programme_id,
                    'period_start' => $result->period_start,
                    'period_end' => $result->period_end,
                    'measure_type' => 'actual',
                    'dimension_key' => "project:{$update->devolution_project_id}:{$result->dimension_key}",
                    'disaggregation' => $result->disaggregation,
                    'numeric_value' => $result->numeric_value,
                    'narrative_value' => $result->narrative_value,
                    'source_reference' => __('projects.references.progress_update', ['project' => $update->project->code, 'date' => $update->reporting_date->toDateString()]),
                    'provenance' => [...$update->provenance, 'source_type' => 'verified_project_progress', 'project_id' => $update->devolution_project_id, 'progress_update_id' => $update->id, 'project_verification_actor_id' => $verificationActor->id, 'project_verified_at' => $update->verified_at?->toIso8601String()],
                    'quality_status' => 'unassessed',
                    'verification_status' => 'submitted',
                    'submitted_by' => $update->submitted_by,
                    'submitted_at' => $update->verified_at ?? now(),
                ],
            );
            if ($observation->wasRecentlyCreated) {
                $this->auditLogger->record($verificationActor, $observation, 'indicator.observation.ingested_from_project', __('projects.audit.result_ingested', ['indicator' => $result->indicator->code, 'project' => $update->project->code]), $result->county_id, ['project_progress_update_id' => $update->id, 'project_indicator_result_id' => $result->id]);
            }
            $observations->push($observation);
        }

        return $observations;
    }
}
