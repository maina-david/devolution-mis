<?php

namespace App\Http\Controllers;

use App\Actions\CreatePerformancePlan;
use App\Actions\DecidePerformanceGoalAmendment;
use App\Actions\RequestPerformanceGoalAmendment;
use App\Actions\TransitionPerformancePlan;
use App\Enums\ProgrammePermission;
use App\Http\Requests\DecidePerformanceGoalAmendmentRequest;
use App\Http\Requests\StorePerformanceCycleRequest;
use App\Http\Requests\StorePerformanceGoalAmendmentRequest;
use App\Http\Requests\StorePerformancePlanRequest;
use App\Http\Requests\TransitionPerformancePlanRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\Organization;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalVersion;
use App\Models\PerformancePlan;
use App\Models\User;
use App\Services\DepartmentalPerformanceAnalytics;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentalPerformanceController extends Controller
{
    public function index(WorkspaceIndexRequest $request, DepartmentalPerformanceAnalytics $analytics, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewDepartmentalPerformance->value);
        $user = $this->user($request);
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedOrganizationIds = collect($referenceDataRelease?->snapshot['organizations'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->values()->all();
        $plans = $this->visiblePlans($user)
            ->with(['cycle:id,code,name', 'employee:id,name,email', 'supervisor:id,name', 'organization:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'goals.versions.creator:id,name', 'goals.amendments.requester:id,name', 'goals.amendments.decision.decider:id,name', 'reviews.reviewer:id,name', 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status'])
            ->withCount('goals')->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('cycle_id'), fn (Builder $query) => $query->where('performance_cycle_id', $request->string('cycle_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $query) => $query->where('job_title', 'ilike', "%{$search}%")->orWhere('hris_employee_reference', 'ilike', "%{$search}%")->orWhereHas('employee', fn (Builder $employee) => $employee->where('name', 'ilike', "%{$search}%")));
            })->latest()->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('departmental-performance/index', [
            'plans' => $plans->through(fn (PerformancePlan $plan): array => $this->payload($plan)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'cycle_id', 'per_page']),
            'capabilities' => ['submit' => $user->can(ProgrammePermission::SubmitPerformancePlans->value), 'review' => $user->can(ProgrammePermission::ReviewPerformancePlans->value), 'manageCycles' => $user->can(ProgrammePermission::ManagePerformanceCycles->value)],
            'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()],
            'options' => ['cycles' => PerformanceCycle::query()->orderByDesc('period_start')->get(['id', 'name', 'status']), 'supervisors' => User::permission(ProgrammePermission::ReviewPerformancePlans->value)->orderBy('name')->get(['id', 'name']), 'organizations' => Organization::query()->where('status', 'active')->whereIn('id', $governedOrganizationIds)->orderBy('name')->get(['id', 'name'])],
            'analytics' => $analytics->summarize($this->filteredPlans($this->visiblePlans($user), $request)),
        ]);
    }

    public function storeCycle(StorePerformanceCycleRequest $request): RedirectResponse
    {
        $cycle = PerformanceCycle::create([...$request->validated(), 'created_by' => $this->user($request)->id]);

        return back()->with('success', "Performance cycle {$cycle->code} created.");
    }

    public function store(StorePerformancePlanRequest $request, CreatePerformancePlan $createPerformancePlan): RedirectResponse
    {
        $createPerformancePlan->handle($this->user($request), $request->validated());

        return back()->with('success', 'Weighted performance plan created.');
    }

    public function transition(TransitionPerformancePlanRequest $request, string $currentTeam, PerformancePlan $performancePlan, TransitionPerformancePlan $transitionPerformancePlan): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePlans($user)->whereKey($performancePlan)->exists(), 403);
        $transitionPerformancePlan->handle($performancePlan, $user, $request->validated());

        return back()->with('success', 'Performance lifecycle updated.');
    }

    public function requestGoalAmendment(StorePerformanceGoalAmendmentRequest $request, string $currentTeam, PerformancePlan $performancePlan, PerformanceGoal $performanceGoal, RequestPerformanceGoalAmendment $requestAmendment): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePlans($user)->whereKey($performancePlan)->exists(), 403);
        $requestAmendment->handle($performancePlan, $performanceGoal, $user, $request->validated());

        return back()->with('success', 'Goal amendment submitted for independent decision.');
    }

    public function decideGoalAmendment(DecidePerformanceGoalAmendmentRequest $request, string $currentTeam, PerformancePlan $performancePlan, PerformanceGoalAmendment $performanceGoalAmendment, DecidePerformanceGoalAmendment $decideAmendment): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePlans($user)->whereKey($performancePlan)->exists(), 403);
        abort_unless($performanceGoalAmendment->performance_plan_id === $performancePlan->id, 404);
        $decideAmendment->handle($performanceGoalAmendment, $user, $request->validated());

        return back()->with('success', 'Goal amendment decision retained.');
    }

    /** @return Builder<PerformancePlan> */
    private function visiblePlans(User $user): Builder
    {
        return PerformancePlan::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('employee_id', $user->id)->orWhere('supervisor_id', $user->id)));
    }

    /**
     * @param  Builder<PerformancePlan>  $plans
     * @return Builder<PerformancePlan>
     */
    private function filteredPlans(Builder $plans, WorkspaceIndexRequest $request): Builder
    {
        return $plans->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('cycle_id'), fn (Builder $query) => $query->where('performance_cycle_id', $request->string('cycle_id')));
    }

    /** @return array<string, mixed> */
    private function payload(PerformancePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'cycle' => $plan->cycle->name,
            'cycleId' => $plan->performance_cycle_id,
            'employee' => $plan->employee->name,
            'employeeId' => $plan->employee_id,
            'supervisor' => $plan->supervisor->name,
            'supervisorId' => $plan->supervisor_id,
            'organization' => $plan->organization?->name,
            'referenceData' => $plan->referenceDataRelease ? ['version' => $plan->referenceDataRelease->version, 'effectiveFrom' => $plan->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $plan->referenceDataRelease->checksum] : null,
            'planType' => $plan->plan_type,
            'hrisReference' => $plan->hris_employee_reference,
            'jobTitle' => $plan->job_title,
            'expectations' => $plan->overall_expectations,
            'status' => $plan->status,
            'selfScore' => $plan->self_score,
            'supervisorScore' => $plan->supervisor_score,
            'finalScore' => $plan->final_score,
            'capacityGapSummary' => $plan->capacity_gap_summary,
            'integrationStatus' => $plan->integration_status,
            'decisionDueAt' => $plan->decision_due_at?->toIso8601String(),
            'goals' => $plan->goals->map(fn (PerformanceGoal $goal): array => [
                'id' => $goal->id, 'code' => $goal->code, 'title' => $goal->title, 'description' => $goal->description, 'kpi' => $goal->kpi, 'unit' => $goal->unit_of_measure, 'baseline' => $goal->baseline_value, 'target' => $goal->target_value, 'actual' => $goal->actual_value, 'weight' => $goal->weight, 'selfRating' => $goal->self_rating, 'supervisorRating' => $goal->supervisor_rating, 'employeeNarrative' => $goal->employee_narrative, 'supervisorComment' => $goal->supervisor_comment, 'evidenceReference' => $goal->evidence_reference,
                'versions' => $goal->versions->map(fn (PerformanceGoalVersion $version): array => ['id' => $version->id, 'version' => $version->version, 'snapshot' => $version->definition_snapshot, 'checksum' => $version->version_checksum, 'createdBy' => $version->creator->name, 'effectiveAt' => $version->effective_at->toIso8601String()])->values()->all(),
                'amendments' => $goal->amendments->map(fn (PerformanceGoalAmendment $amendment): array => ['id' => $amendment->id, 'requestVersion' => $amendment->request_version, 'proposed' => $amendment->proposed_snapshot, 'reason' => $amendment->reason, 'requester' => $amendment->requester->name, 'requestedAt' => $amendment->requested_at->toIso8601String(), 'requestChecksum' => $amendment->request_checksum, 'decision' => $amendment->decision ? ['decision' => $amendment->decision->decision, 'rationale' => $amendment->decision->rationale, 'decider' => $amendment->decision->decider->name, 'decidedAt' => $amendment->decision->decided_at->toIso8601String(), 'checksum' => $amendment->decision->decision_checksum, 'appliedVersionId' => $amendment->decision->applied_version_id] : null])->values()->all(),
            ])->values()->all(),
            'reviews' => $plan->reviews->map(fn ($review): array => ['id' => $review->id, 'reviewer' => $review->reviewer->name, 'stage' => $review->stage, 'rating' => $review->rating, 'comments' => $review->comments, 'capacityGaps' => $review->capacity_gaps, 'developmentActions' => $review->development_actions, 'reviewedAt' => $review->reviewed_at->toIso8601String()])->values()->all(),
            'documents' => $plan->documentLinks->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'purpose' => $link->purpose])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
