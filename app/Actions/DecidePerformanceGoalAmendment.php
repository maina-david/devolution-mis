<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalAmendmentDecision;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PerformanceGoalVersioning;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class DecidePerformanceGoalAmendment
{
    public function __construct(private PerformanceGoalVersioning $versioning, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PerformanceGoalAmendment $amendment, User $decider, array $attributes): PerformanceGoalAmendmentDecision
    {
        abort_unless($decider->can(ProgrammePermission::ReviewPerformancePlans->value), 403);
        abort_if($amendment->requested_by === $decider->id, 403, __('departmental-performance.errors.amendment_self_decision'));

        $decision = DB::transaction(function () use ($amendment, $decider, $attributes): PerformanceGoalAmendmentDecision {
            $locked = PerformanceGoalAmendment::query()->lockForUpdate()->with(['plan', 'goal', 'baseVersion'])->findOrFail($amendment->id);
            abort_unless($locked->plan->supervisor_id === $decider->id, 403);
            abort_unless($locked->plan->status === 'active', 409, __('departmental-performance.errors.amendment_decisions_closed'));
            abort_if($locked->decision()->exists(), 409, __('departmental-performance.errors.amendment_already_decided'));
            abort_unless($locked->goal->versions()->first()?->is($locked->baseVersion), 409, __('departmental-performance.errors.goal_changed'));

            $decisionName = (string) $attributes['decision'];
            $appliedVersion = null;
            if ($decisionName === 'approved') {
                $otherWeight = (float) PerformanceGoal::query()->where('performance_plan_id', $locked->plan->id)->where('id', '!=', $locked->goal->id)->sum('weight');
                abort_if(abs(($otherWeight + (float) $locked->proposed_snapshot['weight']) - 100) > 0.01, 422, __('departmental-performance.errors.weight_total'));
                $locked->goal->update($locked->proposed_snapshot);
                $appliedVersion = $this->versioning->create($locked->goal, $decider, $locked->proposed_snapshot);
            }

            $decidedAt = now();
            $snapshot = ['amendment_id' => $locked->id, 'request_checksum' => $locked->request_checksum, 'decision' => $decisionName, 'rationale' => trim((string) $attributes['rationale']), 'decided_by' => $decider->id, 'decided_at' => $decidedAt->toIso8601String(), 'applied_version_id' => $appliedVersion?->id, 'applied_version_checksum' => $appliedVersion?->version_checksum];

            return $locked->decision()->create([
                'decision' => $decisionName,
                'rationale' => $snapshot['rationale'],
                'decided_by' => $decider->id,
                'decided_at' => $decidedAt,
                'applied_version_id' => $appliedVersion?->id,
                'decision_checksum' => $this->canonicalJson->checksum($snapshot),
                'decision_snapshot' => $snapshot,
            ]);
        }, attempts: 3);

        $this->auditLogger->record($decider, $amendment->plan, 'performance.goal.amendment_decided', __('departmental-performance.audit.amendment_decided', ['version' => $amendment->request_version, 'decision' => $decision->decision]), metadata: ['goal_id' => $amendment->performance_goal_id, 'amendment_id' => $amendment->id, 'decision_id' => $decision->id, 'decision_checksum' => $decision->decision_checksum]);

        return $decision;
    }
}
