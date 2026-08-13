<?php

namespace App\Actions;

use App\Models\PerformanceCycle;
use App\Models\PerformancePlan;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\PerformanceGoalVersioning;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreatePerformancePlan
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger, private PerformanceGoalVersioning $goalVersioning, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): PerformancePlan
    {
        $cycle = PerformanceCycle::query()->whereKey($attributes['performance_cycle_id'])->where('status', 'open')->firstOrFail();

        return DB::transaction(function () use ($actor, $attributes, $cycle): PerformancePlan {
            $goals = collect($this->records($attributes['goals'] ?? null));
            $organizationId = is_string($attributes['organization_id'] ?? null) ? $attributes['organization_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forPerformancePlan($organizationId, now());
            $plan = PerformancePlan::create([...collect($attributes)->except('goals')->all(), 'reference_data_release_id' => $referenceDataRelease->id, 'employee_id' => $actor->id, 'status' => 'draft', 'integration_status' => filled($attributes['hris_employee_reference'] ?? null) ? 'referenced' : 'pending', 'created_by' => $actor->id]);
            foreach ($goals->values() as $sequence => $goal) {
                $createdGoal = $plan->goals()->create([...$goal, 'sequence' => $sequence + 1]);
                $this->goalVersioning->create($createdGoal, $actor, $goal);
            }
            $definition = WorkflowDefinition::query()->where('code', 'DEPARTMENTAL-PERFORMANCE-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $plan, $actor, ['goal_count' => $goals->count(), 'goal_weight_total' => (float) $goals->sum('weight'), 'self_review_complete' => false]);
            $plan->update(['workflow_instance_id' => $instance->id, 'decision_due_at' => $instance->due_at]);
            $this->auditLogger->record($actor, $plan, 'performance.plan.created', trans_choice('departmental-performance.audit.plan_created', $goals->count(), ['cycle' => $cycle->code, 'count' => $goals->count()]), null, ['cycle' => $cycle->code, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $plan->refresh();
        });
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(__('departmental-performance.errors.goals_array'));
        }

        return array_values(array_map(function (mixed $goal): array {
            if (! is_array($goal)) {
                throw new InvalidArgumentException(__('departmental-performance.errors.goal_object'));
            }

            return $goal;
        }, $value));
    }
}
