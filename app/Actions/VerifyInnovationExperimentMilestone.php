<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\InnovationExperimentMilestone;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyInnovationExperimentMilestone
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(InnovationExperimentMilestone $milestone, User $actor, array $attributes): InnovationExperimentMilestone
    {
        abort_unless($actor->can(ProgrammePermission::CurateKnowledge->value), 403, __('knowledge.errors.innovation_milestone_verify_unauthorized'));

        return DB::transaction(function () use ($milestone, $actor, $attributes): InnovationExperimentMilestone {
            $milestone = InnovationExperimentMilestone::query()->with('innovation.county')->lockForUpdate()->findOrFail($milestone->id);
            abort_unless($actor->canAccessCounty($milestone->innovation->county), 403);
            if (! in_array($milestone->status, ['completed', 'failed'], true) || $milestone->verification_decision !== 'pending') {
                throw ValidationException::withMessages(['verification_decision' => __('knowledge.errors.innovation_milestone_terminal_only')]);
            }
            if (in_array($actor->id, array_filter([$milestone->owner_id, $milestone->submitted_by, $milestone->innovation->submitted_by]), true)) {
                throw ValidationException::withMessages(['verification_decision' => __('knowledge.errors.innovation_milestone_verifier_independence')]);
            }
            $milestone->update([
                'verification_decision' => $attributes['verification_decision'],
                'verification_rationale' => $attributes['verification_rationale'],
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $this->auditLogger->record($actor, $milestone, 'knowledge.innovation.milestone-verified', __('knowledge.audit.innovation_milestone_verified', ['title' => $milestone->title, 'decision' => $milestone->verification_decision]), $milestone->innovation->county_id, ['decision' => $milestone->verification_decision, 'rationale' => $milestone->verification_rationale]);

            return $milestone->refresh();
        });
    }
}
