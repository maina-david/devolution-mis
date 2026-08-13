<?php

namespace App\Actions;

use App\Models\PerformanceGoal;
use App\Models\PerformancePlan;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionPerformancePlan
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PerformancePlan $plan, User $actor, array $attributes): PerformancePlan
    {
        $transition = (string) $attributes['transition'];
        $employeeTransitions = ['submit_goals', 'start_review', 'submit_self_review'];
        abort_if(in_array($transition, $employeeTransitions, true) && $plan->employee_id !== $actor->id, 403);
        abort_if(! in_array($transition, $employeeTransitions, true) && $plan->supervisor_id !== $actor->id, 403);

        return DB::transaction(function () use ($plan, $actor, $attributes, $transition): PerformancePlan {
            $context = [];
            $context['goal_plan_evidence_present'] = $this->hasCleanDocument($plan, 'performance-goal-plan');
            $context['self_review_evidence_present'] = $this->hasCleanDocument($plan, 'performance-self-review-evidence');
            $context['final_appraisal_evidence_present'] = $this->hasCleanDocument($plan, 'performance-final-appraisal');
            if (in_array($transition, ['submit_self_review', 'finalize_review'], true)) {
                $score = $this->recordRatings($plan, $attributes['goals'], $transition === 'submit_self_review');
                $context[$transition === 'submit_self_review' ? 'self_review_complete' : 'supervisor_review_complete'] = true;
                $plan->update($transition === 'submit_self_review' ? ['self_score' => $score] : ['supervisor_score' => $score, 'final_score' => $score, 'capacity_gap_summary' => $attributes['capacity_gaps']]);
            }
            $instance = $this->transitionWorkflow->handle($plan->workflowInstance()->firstOrFail(), $transition, $actor, $context, $attributes['rationale']);
            $plan->update(['status' => $instance->current_state, 'submitted_at' => $transition === 'submit_goals' ? now() : $plan->submitted_at, 'decision_due_at' => $instance->due_at, 'finalized_at' => $instance->current_state === 'finalized' ? now() : null]);
            if (in_array($transition, ['approve_goals', 'return_goals', 'finalize_review'], true)) {
                $plan->reviews()->create(['reviewer_id' => $actor->id, 'stage' => $transition, 'rating' => $transition === 'finalize_review' ? $plan->supervisor_score : null, 'comments' => $attributes['rationale'], 'capacity_gaps' => $attributes['capacity_gaps'] ?? null, 'development_actions' => $attributes['development_actions'] ?? null, 'reviewed_at' => now()]);
            }
            $recipient = $actor->id === $plan->employee_id ? $plan->supervisor : $plan->employee;
            $recipient->notify(ProgrammeAlert::translated('departmental-performance.notifications.plan_updated_title', 'departmental-performance.notifications.plan_updated_message', 'departmental-performance', messageParameters: ['state' => $instance->current_state]));
            $this->auditLogger->record($actor, $plan, 'performance.plan.transitioned', __('departmental-performance.audit.plan_transitioned', ['state' => $instance->current_state]), null, ['transition' => $transition]);

            return $plan->refresh();
        });
    }

    private function hasCleanDocument(PerformancePlan $plan, string $purpose): bool
    {
        return $plan->documentLinks()->where('purpose', $purpose)->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
    }

    /** @param list<array<string, mixed>> $ratings */
    private function recordRatings(PerformancePlan $plan, array $ratings, bool $selfReview): float
    {
        $goals = $plan->goals()->get()->keyBy('id');
        abort_unless(count($ratings) === $goals->count(), 422, __('departmental-performance.errors.every_goal_rating'));
        $weightedScore = 0.0;
        foreach ($ratings as $rating) {
            /** @var PerformanceGoal|null $goal */
            $goal = $goals->get($rating['id']);
            abort_unless($goal instanceof PerformanceGoal, 422, __('departmental-performance.errors.rating_outside_plan'));
            $score = (float) $rating['rating'];
            $goal->update($selfReview ? ['actual_value' => $rating['actual_value'] ?? null, 'self_rating' => $score, 'employee_narrative' => $rating['narrative'] ?? null, 'evidence_reference' => $rating['evidence_reference'] ?? null] : ['supervisor_rating' => $score, 'supervisor_comment' => $rating['narrative'] ?? null]);
            $weightedScore += $score * ((float) $goal->weight / 100);
        }

        return round($weightedScore, 2);
    }
}
