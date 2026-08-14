<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentDocument;
use App\Models\InnovationExperimentMilestone;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInnovationExperimentMilestone
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(InnovationExperimentMilestone $milestone, User $actor, array $attributes): InnovationExperimentMilestone
    {
        abort_unless($actor->canAny([ProgrammePermission::ContributeKnowledge->value, ProgrammePermission::ManageKnowledge->value]), 403, __('knowledge.errors.innovation_milestone_update_unauthorized'));

        return DB::transaction(function () use ($milestone, $actor, $attributes): InnovationExperimentMilestone {
            $milestone = InnovationExperimentMilestone::query()->with('innovation.county')->lockForUpdate()->findOrFail($milestone->id);
            abort_unless($actor->canAccessCounty($milestone->innovation->county), 403);
            abort_unless($milestone->owner_id === $actor->id || $actor->can(ProgrammePermission::ManageKnowledge->value), 403);
            if ($milestone->innovation->status !== 'piloting') {
                throw ValidationException::withMessages(['innovation' => __('knowledge.errors.innovation_milestone_pilot_only')]);
            }
            $allowed = ['planned' => ['in_progress'], 'in_progress' => ['completed', 'failed']];
            if (! in_array($attributes['status'], $allowed[$milestone->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('knowledge.errors.innovation_milestone_transition', ['from' => $milestone->status, 'to' => $attributes['status']])]);
            }
            $terminal = in_array($attributes['status'], ['completed', 'failed'], true);
            $document = $terminal ? AssessmentDocument::query()->whereKey($attributes['assessment_document_id'])->sole() : null;
            if ($document && ($document->county_id !== $milestone->innovation->county_id || $document->scan_status !== 'clean' || $document->record_status !== 'active')) {
                throw ValidationException::withMessages(['assessment_document_id' => __('knowledge.errors.innovation_milestone_evidence')]);
            }
            $before = $milestone->only(['status', 'actual_value', 'outcome_summary', 'assessment_document_id']);
            $milestone->update([
                'status' => $attributes['status'],
                'actual_value' => $terminal ? $attributes['actual_value'] : null,
                'outcome_summary' => $terminal ? $attributes['outcome_summary'] : null,
                'assessment_document_id' => $document?->id,
                'submitted_by' => $terminal ? $actor->id : null,
                'submitted_at' => $terminal ? now() : null,
                'verification_decision' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'verification_rationale' => null,
            ]);
            $this->auditLogger->record($actor, $milestone, 'knowledge.innovation.milestone-updated', __('knowledge.audit.innovation_milestone_updated', ['title' => $milestone->title, 'status' => $milestone->status]), $milestone->innovation->county_id, ['before' => $before, 'after' => $milestone->only(['status', 'actual_value', 'outcome_summary', 'assessment_document_id'])]);

            return $milestone->refresh();
        });
    }
}
