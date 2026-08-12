<?php

namespace App\Http\Controllers;

use App\Actions\AllocateProjectResource;
use App\Actions\CreateDevolutionProject;
use App\Actions\CreateProjectResource;
use App\Actions\CreateProjectScheduleBaseline;
use App\Actions\DecideProjectScheduleBaseline;
use App\Actions\RecordProjectProgress;
use App\Actions\TransitionWorkflow;
use App\Actions\UpdateProjectRegisterRecord;
use App\Actions\VerifyProjectProgress;
use App\Enums\ProgrammePermission;
use App\Http\Requests\DecideProjectScheduleBaselineRequest;
use App\Http\Requests\StoreDevolutionProjectRequest;
use App\Http\Requests\StoreProjectBudgetLineRequest;
use App\Http\Requests\StoreProjectMilestoneRequest;
use App\Http\Requests\StoreProjectProcurementRequest;
use App\Http\Requests\StoreProjectProgressUpdateRequest;
use App\Http\Requests\StoreProjectResourceAllocationRequest;
use App\Http\Requests\StoreProjectResourceRequest;
use App\Http\Requests\StoreProjectRiskRequest;
use App\Http\Requests\StoreProjectScheduleBaselineRequest;
use App\Http\Requests\TransitionProjectRequest;
use App\Http\Requests\UpdateProjectBudgetLineRequest;
use App\Http\Requests\UpdateProjectMilestoneRequest;
use App\Http\Requests\UpdateProjectProcurementRequest;
use App\Http\Requests\UpdateProjectRiskRequest;
use App\Http\Requests\VerifyProjectProgressUpdateRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\IndicatorDefinition;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectMilestone;
use App\Models\ProjectProcurement;
use App\Models\ProjectProgressUpdate;
use App\Models\ProjectRisk;
use App\Models\ProjectScheduleBaseline;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\AuditLogger;
use App\Services\ProgrammeCountyScope;
use App\Services\ProjectDependencyGraph;
use App\Services\ProjectEarnedValueAnalyzer;
use App\Services\ProjectScheduleAnalyzer;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectManagementController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeCountyScope $countyScope): Response
    {
        Gate::authorize(ProgrammePermission::ViewProjects->value);
        $user = $this->user($request);
        $filters = WorkspaceFilters::fromRequest($request);
        $projects = DevolutionProject::query()
            ->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyScope->query($user)->select('id')))
            ->with(['leadCounty:id,name,code,logo_path', 'sector:id,name', 'programme:id,name', 'referenceDataRelease:id,version,checksum,effective_from,status'])
            ->withCount(['milestones', 'risks'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('planned_end_date', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('planned_start_date', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('title', 'ilike', '%'.$filters->search.'%')->orWhere('code', 'ilike', '%'.$filters->search.'%')->orWhere('status', 'ilike', '%'.$filters->search.'%')))
            ->latest()->paginate($filters->perPage)->withQueryString();

        return Inertia::render('projects/index', [
            'projects' => $projects->through(fn (DevolutionProject $project): array => [
                'id' => $project->id, 'code' => $project->code, 'title' => $project->title, 'county' => $project->leadCounty->identityCell(),
                'sector' => $project->sector->name, 'programme' => $project->programme?->name, 'stage' => $project->lifecycle_stage,
                'status' => $project->status, 'progress' => $project->physical_progress, 'budget' => $project->approved_budget,
                'expenditure' => $project->actual_expenditure, 'milestones' => $project->milestones_count, 'risks' => $project->risks_count,
                'referenceRelease' => $this->referenceReleasePayload($project),
            ]),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
            'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageProjects->value), 'submitUpdates' => $user->can(ProgrammePermission::SubmitProjectUpdates->value)],
            'options' => [
                'counties' => $countyScope->query($user)->orderBy('code')->get()->map->identityCell()->values(),
                'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'programmes' => Programme::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'organizations' => Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'indicators' => IndicatorDefinition::query()->where('status', 'approved')->orderBy('code')->get(['id', 'code', 'name']),
            ],
        ]);
    }

    public function show(Request $request, DevolutionProject $project, ProjectScheduleAnalyzer $scheduleAnalyzer, ProjectEarnedValueAnalyzer $earnedValueAnalyzer): Response
    {
        Gate::authorize(ProgrammePermission::ViewProjects->value);
        $this->authorizeProject($request, $project);
        $project->load(['leadCounty:id,name,code,logo_path', 'counties:id,name,code,logo_path', 'sector:id,name', 'programme:id,name', 'referenceDataRelease:id,version,checksum,effective_from,status', 'indicators:id,code,name,unit_of_measure,value_type,status,effective_from,effective_to,version', 'milestones', 'scheduleBaselines.requester:id,name', 'scheduleBaselines.decider:id,name', 'budgetLines', 'risks', 'procurements', 'resources.creator:id,name', 'resources.allocations.milestone:id,code,title', 'resources.allocations.creator:id,name', 'progressUpdates.indicatorResults.indicator:id,code,name,unit_of_measure,value_type', 'progressUpdates.indicatorResults.county:id,name,code,logo_path', 'progressUpdates.indicatorResults.observation:id,source_project_indicator_result_id,verification_status,quality_status']);
        $approvedBaseline = $project->scheduleBaselines->where('status', 'approved')->sortByDesc('version')->first();
        $scheduleAnalysis = $project->milestones->isEmpty() ? null : $scheduleAnalyzer->variance($project->milestones, $approvedBaseline, now()->toImmutable());
        $documents = $project->documentLinks()
            ->whereHas('document', fn (Builder $query) => $query->where('record_status', 'active'))
            ->with('document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status,created_at')
            ->latest()
            ->get()
            ->map(fn ($link): array => [
                'id' => $link->document->id,
                'title' => $link->document->title,
                'category' => $link->document->category,
                'sourceType' => $link->document->source_type,
                'originalName' => $link->document->original_name,
                'mimeType' => $link->document->mime_type,
                'scanStatus' => $link->document->scan_status,
                'ocrStatus' => $link->document->ocr_status,
                'uploadedAt' => $link->document->created_at?->toIso8601String(),
                'purpose' => $link->purpose,
            ])
            ->values();

        return Inertia::render('projects/show', [
            'project' => $project,
            'referenceRelease' => $this->referenceReleasePayload($project),
            'documents' => $documents,
            'scheduleBaselines' => $project->scheduleBaselines->sortByDesc('version')->map(fn (ProjectScheduleBaseline $baseline): array => [
                'id' => $baseline->id,
                'version' => $baseline->version,
                'status' => $baseline->status,
                'baselineReason' => $baseline->baseline_reason,
                'snapshotChecksum' => $baseline->snapshot_checksum,
                'decisionChecksum' => $baseline->decision_checksum,
                'requesterId' => $baseline->requested_by,
                'requester' => $baseline->requester->name,
                'decider' => $baseline->decider?->name,
                'decisionRationale' => $baseline->decision_rationale,
                'decidedAt' => $baseline->decided_at?->toIso8601String(),
                'createdAt' => $baseline->created_at?->toIso8601String(),
                'analysis' => $baseline->critical_path_analysis,
            ])->values(),
            'scheduleAnalysis' => $scheduleAnalysis,
            'earnedValueAnalysis' => $earnedValueAnalyzer->analyze($project, $approvedBaseline, now()->toImmutable()),
            'resourcePlan' => $this->resourcePlan($project),
            'resultOptions' => [
                'indicators' => $project->indicators->filter->isCurrentApprovedVersion()->map(fn (IndicatorDefinition $indicator): array => ['id' => $indicator->id, 'code' => $indicator->code, 'name' => $indicator->name, 'unit_of_measure' => $indicator->unit_of_measure, 'value_type' => $indicator->value_type, 'status' => $indicator->status])->values(),
                'counties' => $project->counties->map->identityCell()->values(),
            ],
            'capabilities' => ['manage' => $request->user()?->can(ProgrammePermission::ManageProjects->value), 'submitUpdates' => $request->user()?->can(ProgrammePermission::SubmitProjectUpdates->value), 'verifyUpdates' => $request->user()?->can(ProgrammePermission::VerifyProjectUpdates->value), 'uploadDocuments' => $request->user()?->canAny([ProgrammePermission::ManageProjects->value, ProgrammePermission::SubmitProjectUpdates->value])],
        ]);
    }

    public function store(StoreDevolutionProjectRequest $request, CreateDevolutionProject $create): RedirectResponse
    {
        $project = $create->handle($this->user($request), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project initiated in the governed lifecycle.']);

        return to_route('projects.show', $project);
    }

    /** @return array{version: int, checksum: string, effectiveFrom: string, status: string}|null */
    private function referenceReleasePayload(DevolutionProject $project): ?array
    {
        $release = $project->referenceDataRelease;

        return $release ? [
            'version' => $release->version,
            'checksum' => $release->checksum,
            'effectiveFrom' => $release->effective_from?->toDateString() ?? '—',
            'status' => $release->status,
        ] : null;
    }

    public function storeMilestone(StoreProjectMilestoneRequest $request, DevolutionProject $project, AuditLogger $audit, ProjectDependencyGraph $dependencyGraph): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_if(((float) $project->milestones()->sum('weight') + (float) $request->validated('weight')) > 100, 422, 'Total milestone weight cannot exceed 100%.');
        /** @var list<string> $dependencyIds */
        $dependencyIds = $request->validated('dependencies', []);
        $dependencyGraph->validate($project, null, $dependencyIds);
        $milestone = $project->milestones()->create($request->validated());
        $audit->record($this->user($request), $milestone, 'project.milestone_created', "Milestone {$milestone->code} created.", $project->lead_county_id);

        return $this->success('Milestone created.');
    }

    public function storeScheduleBaseline(StoreProjectScheduleBaselineRequest $request, DevolutionProject $project, CreateProjectScheduleBaseline $create): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $create->handle($project, $this->user($request), $request->string('baseline_reason')->toString());

        return $this->success('Schedule baseline submitted for independent approval.');
    }

    public function decideScheduleBaseline(DecideProjectScheduleBaselineRequest $request, DevolutionProject $project, ProjectScheduleBaseline $scheduleBaseline, DecideProjectScheduleBaseline $decide): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($scheduleBaseline->devolution_project_id === $project->id, 404);
        $decide->handle($scheduleBaseline, $this->user($request), $request->string('decision')->toString(), $request->string('decision_rationale')->toString());

        return $this->success('Schedule baseline decision recorded.');
    }

    public function storeBudgetLine(StoreProjectBudgetLineRequest $request, DevolutionProject $project, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $line = $project->budgetLines()->create($request->validated());
        $project->update([
            'committed_amount' => $project->budgetLines()->sum('committed_amount'),
            'actual_expenditure' => $project->budgetLines()->sum('actual_amount'),
        ]);
        $audit->record($this->user($request), $line, 'project.budget_line_created', "Budget line {$line->code} created.", $project->lead_county_id);

        return $this->success('Budget line created.');
    }

    public function storeResource(StoreProjectResourceRequest $request, DevolutionProject $project, CreateProjectResource $create): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $create->handle($project, $this->user($request), $request->validated());

        return $this->success('Project resource created.');
    }

    public function storeResourceAllocation(StoreProjectResourceAllocationRequest $request, DevolutionProject $project, AllocateProjectResource $allocate): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $allocate->handle($project, $this->user($request), $request->validated());

        return $this->success('Resource allocation created within capacity.');
    }

    public function storeRisk(StoreProjectRiskRequest $request, DevolutionProject $project, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $risk = $project->risks()->create($request->validated());
        $audit->record($this->user($request), $risk, 'project.risk_created', "Risk {$risk->code} created.", $project->lead_county_id, ['rating' => (int) $risk->probability * (int) $risk->impact]);

        return $this->success('Risk registered.');
    }

    public function storeProcurement(StoreProjectProcurementRequest $request, DevolutionProject $project, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $procurement = $project->procurements()->create($request->validated());
        $audit->record($this->user($request), $procurement, 'project.procurement_created', "Procurement {$procurement->reference} created.", $project->lead_county_id);

        return $this->success('Procurement item created.');
    }

    public function updateMilestone(UpdateProjectMilestoneRequest $request, DevolutionProject $project, ProjectMilestone $milestone, UpdateProjectRegisterRecord $update): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $update->handle($project, $milestone, $this->user($request), $request->validated());

        return $this->success('Milestone amendment recorded.');
    }

    public function updateBudgetLine(UpdateProjectBudgetLineRequest $request, DevolutionProject $project, ProjectBudgetLine $budgetLine, UpdateProjectRegisterRecord $update): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $update->handle($project, $budgetLine, $this->user($request), $request->validated());

        return $this->success('Budget amendment recorded.');
    }

    public function updateRisk(UpdateProjectRiskRequest $request, DevolutionProject $project, ProjectRisk $risk, UpdateProjectRegisterRecord $update): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $update->handle($project, $risk, $this->user($request), $request->validated());

        return $this->success('Risk amendment recorded.');
    }

    public function updateProcurement(UpdateProjectProcurementRequest $request, DevolutionProject $project, ProjectProcurement $procurement, UpdateProjectRegisterRecord $update): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $update->handle($project, $procurement, $this->user($request), $request->validated());

        return $this->success('Procurement amendment recorded.');
    }

    public function storeProgress(StoreProjectProgressUpdateRequest $request, DevolutionProject $project, RecordProjectProgress $record): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $record->handle($project, $this->user($request), $request->validated());

        return $this->success('Progress update submitted for verification.');
    }

    public function transition(TransitionProjectRequest $request, DevolutionProject $project, TransitionWorkflow $transition): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $workflowInstance = $project->workflowInstance;
        abort_unless($workflowInstance instanceof WorkflowInstance, 409, 'Project lifecycle workflow is unavailable.');
        $closureReportPresent = $project->documentLinks()->where('purpose', 'project-closure-report')->whereHas('document', fn (Builder $query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
        $instance = $transition->handle($workflowInstance, $request->string('transition')->toString(), $this->user($request), ['physical_progress' => (float) $project->physical_progress, 'closure_report_present' => $closureReportPresent], $request->string('comment')->toString());
        $project->update(['lifecycle_stage' => $instance->current_state, 'status' => $instance->status === 'completed' ? 'closed' : $project->status]);

        return $this->success('Project lifecycle advanced.');
    }

    public function verifyProgress(VerifyProjectProgressUpdateRequest $request, DevolutionProject $project, ProjectProgressUpdate $progressUpdate, VerifyProjectProgress $verify): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($progressUpdate->devolution_project_id === $project->id, 404);
        $verify->handle($progressUpdate, $this->user($request), $request->string('status')->toString(), $request->string('rationale')->toString());

        return $this->success('Progress verification decision recorded.');
    }

    private function authorizeProject(Request $request, DevolutionProject $project): void
    {
        $user = $this->user($request);
        abort_unless($project->counties()->get()->contains(fn (County $county): bool => $user->canAccessCounty($county)), 403);
    }

    /** @return list<array<string, mixed>> */
    private function resourcePlan(DevolutionProject $project): array
    {
        $plan = [];
        foreach ($project->resources as $resource) {
            $allocations = [];
            foreach ($resource->allocations->sortBy('starts_on') as $allocation) {
                $allocations[] = [
                    'id' => $allocation->id,
                    'milestoneId' => $allocation->project_milestone_id,
                    'milestone' => "{$allocation->milestone->code} · {$allocation->milestone->title}",
                    'startsOn' => $allocation->starts_on->toDateString(),
                    'endsOn' => $allocation->ends_on->toDateString(),
                    'plannedUnitsPerDay' => (float) $allocation->planned_units_per_day,
                    'plannedUnits' => (float) $allocation->planned_units,
                    'plannedCost' => (float) $allocation->planned_cost,
                    'notes' => $allocation->notes,
                    'checksum' => $allocation->allocation_checksum,
                    'creator' => $allocation->creator->name,
                ];
            }
            $plan[] = [
                'id' => $resource->id,
                'code' => $resource->code,
                'name' => $resource->name,
                'type' => $resource->resource_type,
                'capacityUnit' => $resource->capacity_unit,
                'capacityPerDay' => (float) $resource->capacity_per_day,
                'costRate' => (float) $resource->cost_rate,
                'currency' => $resource->currency,
                'availableFrom' => $resource->available_from->toDateString(),
                'availableTo' => $resource->available_to->toDateString(),
                'status' => $resource->status,
                'creator' => $resource->creator->name,
                'plannedCost' => (float) $resource->allocations->sum('planned_cost'),
                'allocations' => $allocations,
            ];
        }

        return $plan;
    }

    private function user(Request $request): User
    { /** @var User $user */ $user = $request->user();

        return $user;
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
