<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Services\AuditLogger;
use App\Services\BusinessTimeCalculator;
use App\Services\WorkflowRuleEvaluator;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionWorkflow
{
    public function __construct(private WorkflowRuleEvaluator $ruleEvaluator, private AuditLogger $auditLogger, private BusinessTimeCalculator $businessTime) {}

    /** @param array<string, mixed> $contextChanges */
    public function handle(WorkflowInstance $workflowInstance, string $transitionName, User $actor, array $contextChanges = [], ?string $comment = null): WorkflowInstance
    {
        return DB::transaction(function () use ($workflowInstance, $transitionName, $actor, $contextChanges, $comment): WorkflowInstance {
            $instance = WorkflowInstance::query()->with(['version', 'businessCalendar.holidays'])->lockForUpdate()->findOrFail($workflowInstance->id);
            abort_unless($instance->status === 'active', 409, 'Only active workflow instances can transition.');

            $transition = $this->findTransition($instance, $transitionName);
            $this->authorize($instance, $transition, $actor);
            $context = array_replace_recursive($instance->context, $contextChanges);
            $evaluation = $this->ruleEvaluator->evaluate($this->rules($instance, $transition), $context);

            if (! $evaluation['passed']) {
                throw ValidationException::withMessages(['transition' => 'The workflow transition rules were not satisfied.']);
            }

            $occurredAt = now();
            $previousStateEnteredAt = $instance->state_entered_at;
            $toState = (string) $transition['to'];
            $isTerminal = ($transition['terminal'] ?? false) === true || in_array($toState, Arr::wrap(data_get($instance->version->configuration, 'terminal_states', [])), true);

            $instance->update([
                'current_state' => $toState,
                'context' => $context,
                'state_entered_at' => $occurredAt,
                'due_at' => $isTerminal ? null : $this->dueAt($instance, $transition, $toState, $occurredAt),
                'status' => $isTerminal ? 'completed' : 'active',
                'completed_at' => $isTerminal ? $occurredAt : null,
            ]);

            WorkflowTransition::create([
                'workflow_instance_id' => $instance->id,
                'transition_name' => $transitionName,
                'from_state' => $transition['from'],
                'to_state' => $toState,
                'actor_id' => $actor->id,
                'comment' => $comment,
                'rule_evaluation' => $evaluation,
                'context_snapshot' => $context,
                'occurred_at' => $occurredAt,
            ]);

            $instance->escalations()
                ->where('status', 'open')
                ->where('state_entered_at', $previousStateEnteredAt)
                ->update(['status' => 'resolved', 'resolved_at' => $occurredAt]);

            $this->auditLogger->record($actor, $instance, 'workflow.instance.transitioned', "Workflow transitioned from {$transition['from']} to {$toState}.", $instance->county_id, ['transition' => $transitionName, 'rule_evaluation' => $evaluation]);

            return $instance->refresh();
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function findTransition(WorkflowInstance $instance, string $name): array
    {
        $transitions = data_get($instance->version->configuration, 'transitions', []);

        foreach (is_array($transitions) ? $transitions : [] as $transition) {
            if (is_array($transition) && ($transition['name'] ?? null) === $name && ($transition['from'] ?? null) === $instance->current_state) {
                return $transition;
            }
        }

        throw ValidationException::withMessages(['transition' => "Transition [{$name}] is not available from state [{$instance->current_state}]."]);
    }

    /** @param array<string, mixed> $transition */
    private function authorize(WorkflowInstance $instance, array $transition, User $actor): void
    {
        $permission = $transition['permission'] ?? null;
        if (is_string($permission) && ! $actor->can($permission)) {
            throw new AuthorizationException('You are not authorized to perform this workflow transition.');
        }

        $separationFrom = Arr::wrap($transition['separation_from'] ?? []);
        if ($separationFrom !== [] && $instance->transitions()->whereIn('transition_name', $separationFrom)->where('actor_id', $actor->id)->exists()) {
            throw new AuthorizationException('Separation of duties prevents the same actor from performing this transition.');
        }
    }

    /**
     * @param  array<string, mixed>  $transition
     * @return list<array<string, mixed>>
     */
    private function rules(WorkflowInstance $instance, array $transition): array
    {
        $globalRules = data_get($instance->version->configuration, 'rules', []);
        $transitionRules = $transition['rules'] ?? [];

        return [...$this->normalizeRules($globalRules), ...$this->normalizeRules($transitionRules)];
    }

    /** @return list<array<string, mixed>> */
    private function normalizeRules(mixed $rules): array
    {
        $normalized = [];

        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (is_array($rule)) {
                $normalized[] = $rule;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $transition */
    private function dueAt(WorkflowInstance $instance, array $transition, string $state, CarbonInterface $enteredAt): ?CarbonInterface
    {
        $hours = $transition['sla_hours'] ?? data_get($instance->version->configuration, "state_slas.{$state}");

        if (! is_numeric($hours) || $hours <= 0) {
            return null;
        }

        return $instance->businessCalendar ? $this->businessTime->addHours($instance->businessCalendar, $enteredAt, (float) $hours) : $enteredAt->copy()->addHours((float) $hours);
    }
}
