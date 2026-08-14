<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\InnovationReplication;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInnovationReplication
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(InnovationReplication $replication, User $actor, array $attributes): InnovationReplication
    {
        abort_unless($actor->canAny([ProgrammePermission::ContributeKnowledge->value, ProgrammePermission::ManageKnowledge->value]), 403, __('innovation-replications.errors.update_unauthorized'));

        return DB::transaction(function () use ($replication, $actor, $attributes): InnovationReplication {
            $replication = InnovationReplication::query()->with(['targetCounty', 'documentLinks.document'])->lockForUpdate()->findOrFail($replication->id);
            abort_unless($actor->canAccessCounty($replication->targetCounty), 403, __('innovation-replications.errors.county_outside_scope'));
            abort_unless($actor->id === $replication->accountable_user_id || $actor->can(ProgrammePermission::ManageKnowledge->value), 403, __('innovation-replications.errors.accountable_or_manager_required'));
            $transition = (string) $attributes['transition'];
            $updates = collect($attributes)->only(['adaptation_plan', 'success_measure', 'baseline_value', 'target_value', 'actual_value', 'outcome_summary'])->filter(fn (mixed $value): bool => $value !== null)->all();
            $actualValue = $updates['actual_value'] ?? $replication->actual_value;
            $outcomeSummary = $updates['outcome_summary'] ?? $replication->outcome_summary;
            if ($transition === 'submit_verification' && ($actualValue === null || ! filled($outcomeSummary))) {
                throw ValidationException::withMessages(['actual_value' => __('innovation-replications.errors.measured_outcome_required')]);
            }
            $hasCleanEvidence = $replication->documentLinks->contains(fn ($link): bool => $link->purpose === 'innovation-replication-evidence' && $link->document->scan_status === 'clean' && $link->document->record_status === 'active');
            if ($transition === 'submit_verification' && ! $hasCleanEvidence) {
                throw ValidationException::withMessages(['document' => __('innovation-replications.errors.clean_evidence_required')]);
            }
            $replication->update($updates);
            $instance = $this->transitionWorkflow->handle($replication->workflowInstance()->firstOrFail(), $transition, $actor, ['adaptation_ready' => filled($replication->adaptation_plan), 'measure_ready' => filled($replication->success_measure), 'outcome_ready' => $replication->actual_value !== null && filled($replication->outcome_summary), 'evidence_ready' => $hasCleanEvidence], $attributes['rationale']);
            $replication->update([
                'status' => $instance->current_state,
                'submitted_by' => $transition === 'submit_verification' ? $actor->id : $replication->submitted_by,
                'submitted_at' => $transition === 'submit_verification' ? now() : $replication->submitted_at,
                'verification_decision' => $transition === 'submit_verification' ? 'pending' : $replication->verification_decision,
                'verification_rationale' => $transition === 'submit_verification' ? null : $replication->verification_rationale,
                'verified_by' => $transition === 'submit_verification' ? null : $replication->verified_by,
                'verified_at' => $transition === 'submit_verification' ? null : $replication->verified_at,
                'decision_checksum' => $transition === 'submit_verification' ? null : $replication->decision_checksum,
            ]);
            $this->auditLogger->record($actor, $replication, 'knowledge.innovation_replication.transitioned', __('innovation-replications.audit.transitioned', ['reference' => $replication->reference, 'state' => $instance->current_state]), $replication->target_county_id, ['transition' => $transition]);

            return $replication->refresh();
        });
    }
}
