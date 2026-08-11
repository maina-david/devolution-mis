<?php

namespace App\Http\Controllers;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Http\Requests\SimulateWorkflowRequest;
use App\Http\Requests\StoreWorkflowDefinitionRequest;
use App\Http\Requests\StoreWorkflowVersionRequest;
use App\Http\Requests\UpdateWorkflowVersionRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\BusinessCalendar;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\AuditLogger;
use App\Services\WorkflowSimulator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowDefinitionController extends Controller
{
    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ManageWorkflows->value);
        $search = $request->string('search')->trim()->toString();

        $workflows = WorkflowDefinition::query()
            ->with(['versions' => fn ($query) => $query->with('publisher:id,name')->latest('version')])
            ->withCount([
                'instances as active_instances_count' => fn ($query) => $query->where('workflow_instances.status', 'active'),
                'instances as overdue_instances_count' => fn ($query) => $query->where('workflow_instances.status', 'active')->whereNotNull('workflow_instances.due_at')->where('workflow_instances.due_at', '<=', now()),
            ])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhere('module', 'ilike', "%{$search}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('workflows/index', [
            'calendars' => BusinessCalendar::query()->with(['holidays.creator:id,name', 'creator:id,name', 'publisher:id,name'])->latest('version')->orderBy('code')->get()->map(fn (BusinessCalendar $calendar): array => ['id' => $calendar->id, 'code' => $calendar->code, 'version' => $calendar->version, 'name' => $calendar->name, 'timezone' => $calendar->timezone, 'workingDays' => $calendar->working_days, 'workdayStartsAt' => $calendar->workday_starts_at, 'workdayEndsAt' => $calendar->workday_ends_at, 'effectiveFrom' => $calendar->effective_from->toDateString(), 'effectiveTo' => $calendar->effective_to?->toDateString(), 'status' => $calendar->status, 'creator' => $calendar->creator->name, 'publisher' => $calendar->published_by ? $calendar->publisher->name : null, 'publishedAt' => $calendar->published_at?->toIso8601String(), 'checksum' => $calendar->checksum, 'holidays' => $calendar->holidays->map(fn ($holiday): array => ['id' => $holiday->id, 'date' => $holiday->holiday_date->toDateString(), 'name' => $holiday->name, 'category' => $holiday->category, 'sourceReference' => $holiday->source_reference, 'creator' => $holiday->creator->name])->values()->all()])->values()->all(),
            'filters' => ['search' => $search],
            'users' => User::query()->whereNull('access_revoked_at')->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roles->pluck('name')->values()->all()])->values()->all(),
            'workflows' => $workflows->through(fn (WorkflowDefinition $workflow): array => [
                'id' => $workflow->id,
                'code' => $workflow->code,
                'name' => $workflow->name,
                'module' => $workflow->module,
                'description' => $workflow->description,
                'status' => $workflow->status,
                'activeInstances' => (int) $workflow->active_instances_count,
                'overdueInstances' => (int) $workflow->overdue_instances_count,
                'versions' => $workflow->versions->map(fn (WorkflowVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'status' => $version->status,
                    'configuration' => $version->configuration,
                    'checksum' => $version->checksum,
                    'effectiveFrom' => $version->effective_from?->toIso8601String(),
                    'effectiveTo' => $version->effective_to?->toIso8601String(),
                    'publishedBy' => $version->publisher?->name,
                    'publishedAt' => $version->published_at?->toIso8601String(),
                ])->values()->all(),
            ]),
        ]);
    }

    public function simulate(SimulateWorkflowRequest $request, string $currentTeam, WorkflowDefinition $workflowDefinition, WorkflowVersion $workflowVersion, WorkflowSimulator $simulator): JsonResponse
    {
        abort_unless($workflowVersion->workflow_definition_id === $workflowDefinition->id, 404);

        /** @var array{started_at: string, started_by: string, initial_context: array<string, mixed>, steps: list<array{transition_name: string, actor_id: string, context_changes: array<string, mixed>, occurred_at?: string|null}>} $scenario */
        $scenario = $request->validated();

        return response()->json(['simulation' => $simulator->simulate($workflowVersion, $scenario)]);
    }

    public function store(StoreWorkflowDefinitionRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $workflow = WorkflowDefinition::create($request->validated());
        $this->audit($request, $auditLogger, $workflow, 'workflow.definition.created', 'Workflow definition created.');

        return $this->success('Workflow definition created.');
    }

    public function storeVersion(StoreWorkflowVersionRequest $request, string $currentTeam, WorkflowDefinition $workflowDefinition, AuditLogger $auditLogger): RedirectResponse
    {
        $version = DB::transaction(function () use ($request, $workflowDefinition): WorkflowVersion {
            $lockedDefinition = WorkflowDefinition::query()->lockForUpdate()->findOrFail($workflowDefinition->id);

            return $lockedDefinition->versions()->create([
                ...$request->validated(),
                'version' => ((int) $lockedDefinition->versions()->withTrashed()->max('version')) + 1,
                'status' => 'draft',
            ]);
        }, attempts: 3);
        $this->audit($request, $auditLogger, $version, 'workflow.version.created', "Workflow version {$version->version} drafted.");

        return $this->success("Workflow version {$version->version} created as a draft.");
    }

    public function updateVersion(UpdateWorkflowVersionRequest $request, string $currentTeam, WorkflowDefinition $workflowDefinition, WorkflowVersion $workflowVersion, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($workflowVersion->workflow_definition_id === $workflowDefinition->id, 404);
        $workflowVersion->update([...$request->validated(), 'checksum' => null]);
        $this->audit($request, $auditLogger, $workflowVersion, 'workflow.version.updated', "Workflow version {$workflowVersion->version} updated.");

        return $this->success('Draft workflow version updated.');
    }

    public function publish(Request $request, string $currentTeam, WorkflowDefinition $workflowDefinition, WorkflowVersion $workflowVersion, PublishWorkflowVersion $publishWorkflowVersion, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageWorkflows->value);
        abort_unless($workflowVersion->workflow_definition_id === $workflowDefinition->id, 404);
        /** @var User $user */
        $user = $request->user();
        $published = $publishWorkflowVersion->handle($workflowVersion, $user);
        $this->audit($request, $auditLogger, $published, 'workflow.version.published', "Workflow version {$published->version} published.");

        return $this->success("Workflow version {$published->version} published.");
    }

    public function destroyVersion(Request $request, string $currentTeam, WorkflowDefinition $workflowDefinition, WorkflowVersion $workflowVersion, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageWorkflows->value);
        abort_unless($workflowVersion->workflow_definition_id === $workflowDefinition->id, 404);
        abort_unless($workflowVersion->status === 'draft', 409, 'Published or retired workflow versions cannot be archived.');
        $this->audit($request, $auditLogger, $workflowVersion, 'workflow.version.archived', "Workflow version {$workflowVersion->version} archived.");
        $workflowVersion->delete();

        return $this->success('Draft workflow version archived.');
    }

    private function audit(Request $request, AuditLogger $auditLogger, WorkflowDefinition|WorkflowVersion $subject, string $action, string $description): void
    {
        /** @var User $user */
        $user = $request->user();
        $auditLogger->record($user, $subject, $action, $description);
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
