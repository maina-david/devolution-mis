<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ManageKnowledge->value), 403, __('innovation-replications.errors.create_unauthorized'));

        return DB::transaction(function () use ($actor, $attributes): InnovationReplication {
            $innovation = DevolutionInnovation::query()->with('county')->lockForUpdate()->whereKey((string) $attributes['devolution_innovation_id'])->firstOrFail();
            if ($innovation->status !== 'scaling') {
                throw ValidationException::withMessages(['devolution_innovation_id' => __('innovation-replications.errors.scale_ready_required')]);
            }
            if ($innovation->county_id === null) {
                throw ValidationException::withMessages(['devolution_innovation_id' => __('innovation-replications.errors.source_county_required')]);
            }
            if ($innovation->reference_data_release_id === null) {
                throw ValidationException::withMessages(['devolution_innovation_id' => __('innovation-replications.errors.source_lineage_required')]);
            }
            if ($innovation->county_id === $attributes['target_county_id']) {
                throw ValidationException::withMessages(['target_county_id' => __('innovation-replications.errors.different_target_required')]);
            }
            $targetCounty = County::query()->whereKey((string) $attributes['target_county_id'])->firstOrFail();
            abort_unless($actor->canAccessCounty($targetCounty), 403, __('innovation-replications.errors.county_outside_scope'));
            $accountable = User::query()->whereKey((string) $attributes['accountable_user_id'])->firstOrFail();
            if (! $accountable->canAccessCounty($targetCounty)) {
                throw ValidationException::withMessages(['accountable_user_id' => __('innovation-replications.errors.accountable_scope_required')]);
            }
            if (InnovationReplication::withTrashed()->where('devolution_innovation_id', $innovation->id)->where('target_county_id', $targetCounty->id)->exists()) {
                throw ValidationException::withMessages(['target_county_id' => __('innovation-replications.errors.retained_replication_exists')]);
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
            $this->auditLogger->record($actor, $replication, 'knowledge.innovation_replication.created', __('innovation-replications.audit.created', ['reference' => $replication->reference, 'county' => $targetCounty->name]), $replication->target_county_id, ['source_innovation_id' => $innovation->id, 'source_county_id' => $innovation->county_id, 'accountable_user_id' => $accountable->id, 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $replication->refresh();
        });
    }
}
