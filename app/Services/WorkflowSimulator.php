<?php

namespace App\Services;

use App\Models\BusinessCalendar;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class WorkflowSimulator
{
    public function __construct(private WorkflowRuleEvaluator $ruleEvaluator, private BusinessTimeCalculator $businessTime, private CanonicalJson $canonicalJson) {}

    /**
     * @param  array{started_at: string, started_by: string, initial_context: array<string, mixed>, steps: list<array{transition_name: string, actor_id: string, context_changes: array<string, mixed>, occurred_at?: string|null}>}  $scenario
     * @return array<string, mixed>
     */
    public function simulate(WorkflowVersion $version, array $scenario): array
    {
        $configuration = $version->configuration;
        $startedAt = CarbonImmutable::parse($scenario['started_at']);
        $starter = User::query()->findOrFail($scenario['started_by']);
        $initialState = (string) data_get($configuration, 'initial_state');
        $calendar = $this->calendar($configuration, $startedAt);
        $context = $scenario['initial_context'];
        $currentState = $initialState;
        $performed = ['start' => [$starter->id]];
        $startPermission = data_get($configuration, 'start_permission');
        $startAuthorized = ! is_string($startPermission) || $startPermission === '' || $starter->can($startPermission);
        $steps = [];
        $completed = in_array($initialState, Arr::wrap(data_get($configuration, 'terminal_states', [])), true);
        $failure = $startAuthorized ? null : ['code' => 'start_permission_denied', 'message' => __('workflow-management.engine.simulation.start_permission_denied')];

        foreach ($scenario['steps'] as $index => $candidate) {
            if ($failure !== null || $completed) {
                $steps[] = $this->failedStep($index, $candidate, $currentState, $completed ? 'terminal_state_reached' : 'scenario_stopped', $completed ? __('workflow-management.engine.simulation.terminal_state_reached') : __('workflow-management.engine.simulation.scenario_stopped'));

                continue;
            }

            $actor = User::query()->findOrFail($candidate['actor_id']);
            $occurredAt = isset($candidate['occurred_at']) ? CarbonImmutable::parse($candidate['occurred_at']) : $startedAt;
            $transition = $this->transition($configuration, $currentState, $candidate['transition_name']);

            if ($transition === null) {
                $failure = ['code' => 'transition_unavailable', 'message' => __('workflow-management.engine.errors.transition_unavailable', ['transition' => $candidate['transition_name'], 'state' => $currentState])];
                $steps[] = $this->failedStep($index, $candidate, $currentState, $failure['code'], $failure['message'], $actor);

                continue;
            }

            $permission = $transition['permission'] ?? null;
            $authorized = ! is_string($permission) || $permission === '' || $actor->can($permission);
            $separationFrom = Arr::wrap($transition['separation_from'] ?? []);
            $separationPassed = collect($separationFrom)->every(fn (mixed $name): bool => ! in_array($actor->id, $performed[(string) $name] ?? [], true));
            $nextContext = array_replace_recursive($context, $candidate['context_changes']);
            $evaluation = $this->ruleEvaluator->evaluate([...$this->rules(data_get($configuration, 'rules', [])), ...$this->rules($transition['rules'] ?? [])], $nextContext);
            $toState = (string) $transition['to'];
            $terminal = ($transition['terminal'] ?? false) === true || in_array($toState, Arr::wrap(data_get($configuration, 'terminal_states', [])), true);
            $stepFailure = ! $authorized
                ? ['code' => 'permission_denied', 'message' => __('workflow-management.engine.simulation.transition_permission_denied')]
                : (! $separationPassed
                    ? ['code' => 'separation_of_duties_failed', 'message' => __('workflow-management.engine.simulation.separation_failed')]
                    : (! $evaluation['passed'] ? ['code' => 'rules_failed', 'message' => __('workflow-management.engine.simulation.rules_failed')] : null));

            $steps[] = ['index' => $index + 1, 'transitionName' => $candidate['transition_name'], 'fromState' => $currentState, 'toState' => $stepFailure === null ? $toState : null, 'actor' => ['id' => $actor->id, 'name' => $actor->name], 'authorized' => $authorized, 'separationPassed' => $separationPassed, 'ruleEvaluation' => $evaluation, 'status' => $stepFailure === null ? 'passed' : 'failed', 'failureCode' => $stepFailure['code'] ?? null, 'message' => $stepFailure['message'] ?? __('workflow-management.engine.simulation.controls_passed'), 'occurredAt' => $occurredAt->toIso8601String(), 'dueAt' => $stepFailure === null && ! $terminal ? $this->dueAt($configuration, $transition, $toState, $occurredAt, $calendar)?->toIso8601String() : null, 'terminal' => $stepFailure === null && $terminal];

            if ($stepFailure !== null) {
                $failure = $stepFailure;

                continue;
            }

            $context = $nextContext;
            $currentState = $toState;
            $performed[$candidate['transition_name']][] = $actor->id;
            $completed = $terminal;
        }

        return ['passed' => $failure === null, 'completed' => $completed && $failure === null, 'initialState' => $initialState, 'finalState' => $currentState, 'startedAt' => $startedAt->toIso8601String(), 'initialDueAt' => $this->dueAt($configuration, [], $initialState, $startedAt, $calendar)?->toIso8601String(), 'starter' => ['id' => $starter->id, 'name' => $starter->name, 'authorized' => $startAuthorized], 'failureCode' => $failure['code'] ?? null, 'message' => $failure['message'] ?? ($completed ? __('workflow-management.engine.simulation.completed') : __('workflow-management.engine.simulation.active')), 'steps' => $steps, 'version' => ['id' => $version->id, 'number' => $version->version, 'status' => $version->status, 'checksum' => $version->checksum ?? $this->canonicalJson->checksum($configuration)], 'calendar' => $calendar ? ['id' => $calendar->id, 'code' => $calendar->code, 'version' => $calendar->version, 'checksum' => $calendar->checksum] : null, 'scenarioChecksum' => $this->canonicalJson->checksum($scenario)];
    }

    /** @param array<string, mixed> $configuration */
    private function calendar(array $configuration, CarbonInterface $startedAt): ?BusinessCalendar
    {
        $calendarId = data_get($configuration, 'business_calendar_id');

        if (! is_string($calendarId) || $calendarId === '') {
            return null;
        }

        return BusinessCalendar::query()->with('holidays')->whereKey($calendarId)->where('status', 'published')->whereDate('effective_from', '<=', $startedAt)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $startedAt))->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>|null
     */
    private function transition(array $configuration, string $state, string $name): ?array
    {
        foreach (data_get($configuration, 'transitions', []) as $transition) {
            if (is_array($transition) && ($transition['from'] ?? null) === $state && ($transition['name'] ?? null) === $name) {
                return $transition;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function rules(mixed $rules): array
    {
        $normalized = [];

        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (is_array($rule)) {
                $normalized[] = $rule;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $transition
     */
    private function dueAt(array $configuration, array $transition, string $state, CarbonInterface $enteredAt, ?BusinessCalendar $calendar): ?CarbonInterface
    {
        $hours = $transition['sla_hours'] ?? data_get($configuration, "state_slas.{$state}");

        if (! is_numeric($hours) || $hours <= 0) {
            return null;
        }

        return $calendar ? $this->businessTime->addHours($calendar, $enteredAt, (float) $hours) : $enteredAt->copy()->addHours((float) $hours);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function failedStep(int $index, array $candidate, string $state, string $code, string $message, ?User $actor = null): array
    {
        return ['index' => $index + 1, 'transitionName' => $candidate['transition_name'], 'fromState' => $state, 'toState' => null, 'actor' => $actor ? ['id' => $actor->id, 'name' => $actor->name] : null, 'authorized' => false, 'separationPassed' => false, 'ruleEvaluation' => ['passed' => false, 'results' => []], 'status' => 'failed', 'failureCode' => $code, 'message' => $message, 'occurredAt' => $candidate['occurred_at'] ?? null, 'dueAt' => null, 'terminal' => false];
    }
}
