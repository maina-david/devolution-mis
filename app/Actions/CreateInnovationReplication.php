<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\InnovationReplication;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateInnovationReplication
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): InnovationReplication
    {
        return DB::transaction(function () use ($actor, $attributes): InnovationReplication {
            $innovation = DevolutionInnovation::query()->with('county')->lockForUpdate()->whereKey((string) $attributes['devolution_innovation_id'])->firstOrFail();
            if ($innovation->status !== 'scaling') {
                throw ValidationException::withMessages(['devolution_innovation_id' => 'Only independently verified innovations approved for scale-up can be replicated.']);
            }
            if ($innovation->county_id === null) {
                throw ValidationException::withMessages(['devolution_innovation_id' => 'Cross-county replication requires an identified source county.']);
            }
            if ($innovation->reference_data_release_id === null) {
                throw ValidationException::withMessages(['devolution_innovation_id' => 'A scale-ready innovation with verified reference-data lineage is required.']);
            }
            if ($innovation->county_id === $attributes['target_county_id']) {
                throw ValidationException::withMessages(['target_county_id' => 'The replication target must differ from the source county.']);
            }
            $targetCounty = County::query()->whereKey((string) $attributes['target_county_id'])->firstOrFail();
            abort_unless($actor->canAccessCounty($targetCounty), 403);
            $accountable = User::query()->whereKey((string) $attributes['accountable_user_id'])->firstOrFail();
            if (! $accountable->canAccessCounty($targetCounty)) {
                throw ValidationException::withMessages(['accountable_user_id' => 'The accountable adopter must be authorized for the target county.']);
            }
            if (InnovationReplication::withTrashed()->where('devolution_innovation_id', $innovation->id)->where('target_county_id', $targetCounty->id)->exists()) {
                throw ValidationException::withMessages(['target_county_id' => 'This innovation already has a retained replication record for the target county.']);
            }
            $release = $this->referenceDataReleaseResolver->forInnovationReplication($innovation->county_id, $targetCounty->id, now());

            $replication = InnovationReplication::create([
                ...$attributes,
                'source_county_id' => $innovation->county_id,
                'reference_data_release_id' => $release->id,
                'created_by' => $actor->id,
                'reference' => 'REP-'.now()->format('Y').'-'.mb_strtoupper(Str::random(10)),
                'status' => 'planned',
                'verification_decision' => 'pending',
            ]);
            $definition = WorkflowDefinition::query()->where('code', 'KNOWLEDGE-INNOVATION-REPLICATION')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $replication, $actor, ['adaptation_ready' => filled($replication->adaptation_plan), 'measure_ready' => filled($replication->success_measure)], $replication->target_county_id);
            $replication->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($actor, $replication, 'knowledge.innovation_replication.created', "Replication {$replication->reference} created for {$targetCounty->name}.", $replication->target_county_id, ['source_innovation_id' => $innovation->id, 'source_county_id' => $innovation->county_id, 'accountable_user_id' => $accountable->id, 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $replication->refresh();
        });
    }
}
