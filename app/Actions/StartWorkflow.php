<?php

namespace App\Actions;

use App\Models\BusinessCalendar;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\AuditLogger;
use App\Services\BusinessTimeCalculator;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartWorkflow
{
    public function __construct(private AuditLogger $auditLogger, private BusinessTimeCalculator $businessTime) {}

    /** @param array<string, mixed> $context */
    public function handle(WorkflowDefinition $definition, Model $subject, User $actor, array $context = [], ?string $countyId = null): WorkflowInstance
    {
        return DB::transaction(function () use ($definition, $subject, $actor, $context, $countyId): WorkflowInstance {
            $version = $this->activeVersion($definition);
            $configuration = $version->configuration;
            $initialState = Arr::get($configuration, 'initial_state');

            if (! is_string($initialState) || $initialState === '') {
                throw new RuntimeException('Published workflow has no valid initial state.');
            }

            $permission = Arr::get($configuration, 'start_permission');
            if (is_string($permission) && ! $actor->can($permission)) {
                throw new AuthorizationException('You are not authorized to start this workflow.');
            }

            $startedAt = now();
            $businessCalendar = $this->businessCalendar($configuration, $startedAt);
            $instance = WorkflowInstance::create([
                'workflow_version_id' => $version->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (string) $subject->getKey(),
                'county_id' => $countyId,
                'business_calendar_id' => $businessCalendar?->id,
                'current_state' => $initialState,
                'status' => 'active',
                'context' => $context,
                'started_by' => $actor->id,
                'started_at' => $startedAt,
                'state_entered_at' => $startedAt,
                'due_at' => $this->dueAt($configuration, $initialState, $startedAt, $businessCalendar),
            ]);

            WorkflowTransition::create([
                'workflow_instance_id' => $instance->id,
                'transition_name' => 'start',
                'from_state' => null,
                'to_state' => $initialState,
                'actor_id' => $actor->id,
                'rule_evaluation' => ['passed' => true, 'results' => []],
                'context_snapshot' => $context,
                'occurred_at' => $startedAt,
            ]);

            $this->auditLogger->record($actor, $instance, 'workflow.instance.started', "{$definition->name} workflow started.", $countyId, ['state' => $initialState, 'version' => $version->version]);

            return $instance->refresh();
        }, attempts: 3);
    }

    private function activeVersion(WorkflowDefinition $definition): WorkflowVersion
    {
        return $definition->versions()
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->latest('version')
            ->firstOr(fn () => throw new RuntimeException("Workflow [{$definition->code}] has no effective published version."));
    }

    /** @param array<string, mixed> $configuration */
    private function dueAt(array $configuration, string $state, CarbonInterface $enteredAt, ?BusinessCalendar $businessCalendar): ?CarbonInterface
    {
        $hours = data_get($configuration, "state_slas.{$state}");

        if (! is_numeric($hours) || $hours <= 0) {
            return null;
        }

        return $businessCalendar ? $this->businessTime->addHours($businessCalendar, $enteredAt, (float) $hours) : $enteredAt->copy()->addHours((float) $hours);
    }

    /** @param array<string, mixed> $configuration */
    private function businessCalendar(array $configuration, CarbonInterface $startedAt): ?BusinessCalendar
    {
        $calendarId = data_get($configuration, 'business_calendar_id');
        if (! is_string($calendarId) || $calendarId === '') {
            return null;
        }

        return BusinessCalendar::query()->with('holidays')->whereKey($calendarId)->where('status', 'published')->whereDate('effective_from', '<=', $startedAt)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $startedAt))->firstOr(fn () => throw new RuntimeException('The configured business calendar is not published or effective.'));
    }
}
