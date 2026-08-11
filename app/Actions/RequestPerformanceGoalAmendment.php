<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformancePlan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PerformanceGoalVersioning;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class RequestPerformanceGoalAmendment
{
    public function __construct(private PerformanceGoalVersioning $versioning, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PerformancePlan $plan, PerformanceGoal $goal, User $requester, array $attributes): PerformanceGoalAmendment
    {
        abort_unless($requester->can(ProgrammePermission::SubmitPerformancePlans->value) && $plan->employee_id === $requester->id, 403);
        abort_unless($goal->performance_plan_id === $plan->id, 404);
        abort_unless($plan->status === 'active', 409, 'Goal amendments are available only after goal approval and before self-review starts.');

        $amendment = DB::transaction(function () use ($plan, $goal, $requester, $attributes): PerformanceGoalAmendment {
            $lockedPlan = PerformancePlan::query()->lockForUpdate()->findOrFail($plan->id);
            abort_unless($lockedPlan->status === 'active', 409, 'This performance plan no longer accepts amendments.');
            abort_if($lockedPlan->goalAmendments()->whereDoesntHave('decision')->exists(), 409, 'This plan already has a pending goal amendment.');
            $lockedGoal = PerformanceGoal::query()->where('performance_plan_id', $lockedPlan->id)->lockForUpdate()->findOrFail($goal->id);
            $baseVersion = $lockedGoal->versions()->firstOrFail();
            $proposed = $this->versioning->normalize($attributes);
            abort_if($this->canonicalJson->checksum($proposed) === $this->canonicalJson->checksum($baseVersion->definition_snapshot), 422, 'The proposed goal definition is unchanged.');
            $latestRequest = $lockedPlan->goalAmendments()->latest('request_version')->first();
            $requestVersion = $latestRequest === null ? 1 : $latestRequest->request_version + 1;
            $requestedAt = now();
            $payload = ['performance_plan_id' => $lockedPlan->id, 'performance_goal_id' => $lockedGoal->id, 'base_version_id' => $baseVersion->id, 'request_version' => $requestVersion, 'proposed_snapshot' => $proposed, 'reason' => trim((string) $attributes['reason']), 'requested_by' => $requester->id, 'requested_at' => $requestedAt->toIso8601String(), 'predecessor_checksum' => $latestRequest?->request_checksum];

            return $lockedPlan->goalAmendments()->create([...$payload, 'requested_at' => $requestedAt, 'request_checksum' => $this->canonicalJson->checksum($payload)]);
        }, attempts: 3);

        $this->auditLogger->record($requester, $plan, 'performance.goal.amendment_requested', "Goal {$goal->code} amendment request v{$amendment->request_version} recorded.", metadata: ['goal_id' => $goal->id, 'amendment_id' => $amendment->id, 'request_checksum' => $amendment->request_checksum]);

        return $amendment;
    }
}
