<?php

namespace App\Actions;

use App\Models\County;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateProgrammeEvaluation
{
    public function __construct(
        private StartWorkflow $startWorkflow,
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): ProgrammeEvaluation
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        $programmeId = is_string($attributes['programme_id'] ?? null) ? $attributes['programme_id'] : null;

        if ($countyId !== null) {
            $county = County::query()->findOrFail($countyId);
            abort_unless($actor->canAccessCounty($county), 403);
        }

        return DB::transaction(function () use ($actor, $attributes): ProgrammeEvaluation {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $programmeId = is_string($attributes['programme_id'] ?? null) ? $attributes['programme_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forProgrammeEvaluation($programmeId, $countyId, now());
            $evaluation = ProgrammeEvaluation::create([
                ...$attributes,
                'status' => 'planned',
                'created_by' => $actor->id,
                'reference_data_release_id' => $referenceDataRelease->id,
            ]);
            $definition = WorkflowDefinition::query()->where('code', 'PROGRAMME-EVALUATION-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $evaluation, $actor, ['terms_of_reference_present' => false, 'evaluation_report_present' => false], $evaluation->county_id);
            $evaluation->update(['workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
            $this->auditLogger->record($actor, $evaluation, 'programme.evaluation.created', "Evaluation {$evaluation->code} created.", $evaluation->county_id, [
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $evaluation->refresh();
        });
    }
}
