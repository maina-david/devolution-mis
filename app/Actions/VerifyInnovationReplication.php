<?php

namespace App\Actions;

use App\Models\InnovationReplication;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyInnovationReplication
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(InnovationReplication $replication, User $actor, array $attributes): InnovationReplication
    {
        return DB::transaction(function () use ($replication, $actor, $attributes): InnovationReplication {
            $replication = InnovationReplication::query()->with('targetCounty')->lockForUpdate()->findOrFail($replication->id);
            abort_unless($actor->canAccessCounty($replication->targetCounty), 403);
            if (in_array($actor->id, [$replication->created_by, $replication->accountable_user_id, $replication->submitted_by], true)) {
                throw ValidationException::withMessages(['decision' => __('innovation-replications.errors.independent_verifier_required')]);
            }
            $transition = (string) $attributes['decision'];
            $instance = $this->transitionWorkflow->handle($replication->workflowInstance()->firstOrFail(), $transition, $actor, ['independent_verifier' => true], $attributes['rationale']);
            $decision = $transition === 'approve' ? 'approved' : 'returned';
            $evidence = ['replication_id' => $replication->id, 'status' => $instance->current_state, 'decision' => $decision, 'rationale' => $attributes['rationale'], 'verifier_id' => $actor->id, 'actual_value' => $replication->actual_value, 'submitted_at' => $replication->submitted_at?->toIso8601String()];
            $replication->update(['status' => $instance->current_state, 'verification_decision' => $decision, 'verification_rationale' => $attributes['rationale'], 'verified_by' => $actor->id, 'verified_at' => now(), 'decision_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $this->auditLogger->record($actor, $replication, 'knowledge.innovation_replication.verified', __('innovation-replications.audit.verified', ['reference' => $replication->reference, 'decision' => $decision]), $replication->target_county_id, ['decision' => $decision, 'checksum' => $replication->decision_checksum]);

            return $replication->refresh();
        });
    }
}
