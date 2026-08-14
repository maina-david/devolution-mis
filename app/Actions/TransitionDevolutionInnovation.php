<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\DevolutionInnovation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionDevolutionInnovation
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionInnovation $innovation, User $actor, array $attributes): DevolutionInnovation
    {
        $transition = (string) $attributes['transition'];
        $requiredPermission = match ($transition) {
            'submit' => ProgrammePermission::ContributeKnowledge,
            'accept_incubation', 'reject', 'scale' => ProgrammePermission::CurateKnowledge,
            'start_pilot' => ProgrammePermission::ManageKnowledge,
            default => null,
        };
        abort_unless($requiredPermission !== null && $actor->can($requiredPermission->value), 403, __('knowledge.errors.innovation_transition_unauthorized'));

        return DB::transaction(function () use ($innovation, $actor, $attributes): DevolutionInnovation {
            $innovation = DevolutionInnovation::query()->lockForUpdate()->findOrFail($innovation->id);
            abort_unless($actor->canAccessCounty($innovation->county), 403);
            $transition = (string) $attributes['transition'];
            $governance = $this->governanceContext($innovation);
            $this->guardGovernanceGate($transition, $governance);
            $instance = $this->transitionWorkflow->handle($innovation->workflowInstance()->firstOrFail(), $transition, $actor, [
                'incubation_support' => $attributes['incubation_support'] ?? $innovation->incubation_support,
                'evidence_reference' => $attributes['evidence_reference'] ?? $innovation->evidence_reference,
                ...$governance,
            ], $attributes['rationale']);
            $innovation->update([
                'status' => $instance->current_state,
                'stage' => match ($instance->current_state) {
                    'screening' => 'screening', 'incubating' => 'incubation', 'piloting' => 'pilot', 'scaling' => 'scale', 'rejected' => 'closed', default => 'concept',
                },
                'reviewed_by' => $transition !== 'submit' ? $actor->id : $innovation->reviewed_by,
                'submitted_at' => $transition === 'submit' ? now() : $innovation->submitted_at,
                'decision_due_at' => $transition === 'submit' ? now()->addDays(10) : $innovation->decision_due_at,
                'decided_at' => in_array($transition, ['scale', 'reject'], true) ? now() : $innovation->decided_at,
                'incubation_support' => $attributes['incubation_support'] ?? $innovation->incubation_support,
                'evidence_reference' => $attributes['evidence_reference'] ?? $innovation->evidence_reference,
            ]);
            $this->auditLogger->record($actor, $innovation, 'knowledge.innovation.transitioned', __('knowledge.audit.innovation_transitioned', ['reference' => $innovation->reference, 'state' => $instance->current_state]), $innovation->county_id, ['transition' => $transition]);

            return $innovation->refresh();
        });
    }

    /** @return array{panel_ready: bool, panel_score: float|null, funding_ready: bool, milestones_defined: bool, pilot_verified: bool} */
    private function governanceContext(DevolutionInnovation $innovation): array
    {
        $reviews = $innovation->panelReviews()->get(['weighted_score', 'recommendation']);
        $average = $reviews->isEmpty() ? null : round((float) $reviews->avg('weighted_score'), 2);
        $latestFunding = $innovation->fundingDecisions()->latest('decision_version')->first();
        $milestones = $innovation->experimentMilestones()->get(['status', 'verification_decision']);

        return [
            'panel_ready' => $reviews->count() >= 2 && $reviews->where('recommendation', 'advance')->count() >= 2 && $average !== null && $average >= 70,
            'panel_score' => $average,
            'funding_ready' => $latestFunding !== null && in_array($latestFunding->decision, ['approved', 'not_required'], true),
            'milestones_defined' => $milestones->isNotEmpty(),
            'pilot_verified' => $milestones->isNotEmpty() && $milestones->every(fn ($milestone): bool => $milestone->status === 'completed' && $milestone->verification_decision === 'verified'),
        ];
    }

    /** @param array{panel_ready: bool, panel_score: float|null, funding_ready: bool, milestones_defined: bool, pilot_verified: bool} $governance */
    private function guardGovernanceGate(string $transition, array $governance): void
    {
        if ($transition === 'accept_incubation' && ! $governance['panel_ready']) {
            throw ValidationException::withMessages(['transition' => __('knowledge.errors.innovation_incubation_panel_gate')]);
        }
        if ($transition === 'start_pilot' && (! $governance['funding_ready'] || ! $governance['milestones_defined'])) {
            throw ValidationException::withMessages(['transition' => __('knowledge.errors.innovation_pilot_gate')]);
        }
        if ($transition === 'scale' && ! $governance['pilot_verified']) {
            throw ValidationException::withMessages(['transition' => __('knowledge.errors.innovation_scale_gate')]);
        }
    }
}
