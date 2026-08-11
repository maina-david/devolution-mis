<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateDevolutionInnovation
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): DevolutionInnovation
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        if ($countyId !== null) {
            abort_unless($actor->canAccessCounty(County::query()->findOrFail($countyId)), 403);
        }

        return DB::transaction(function () use ($actor, $attributes): DevolutionInnovation {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $sectorId = is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forDevolutionInnovation($countyId, $sectorId, now());
            $innovation = DevolutionInnovation::create([
                ...$attributes,
                'reference_data_release_id' => $referenceDataRelease->id,
                'submitted_by' => $actor->id,
                'reference' => 'INN-'.now()->format('Y').'-'.str_pad((string) (DevolutionInnovation::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
                'stage' => 'concept',
                'status' => 'draft',
            ]);
            $definition = WorkflowDefinition::query()->where('code', 'KNOWLEDGE-INNOVATION-INCUBATION')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $innovation, $actor, [
                'problem_defined' => filled($innovation->problem_statement),
                'solution_defined' => filled($innovation->proposed_solution),
                'impact_defined' => filled($innovation->expected_impact),
            ], $innovation->county_id);
            $innovation->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($actor, $innovation, 'knowledge.innovation.created', "Innovation {$innovation->reference} created.", $innovation->county_id, ['reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $innovation->refresh();
        });
    }
}
