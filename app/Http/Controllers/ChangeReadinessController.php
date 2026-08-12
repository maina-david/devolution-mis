<?php

namespace App\Http\Controllers;

use App\Actions\CreateRolloutWave;
use App\Actions\CreateTrainingCohort;
use App\Actions\CreateUatCampaign;
use App\Actions\CreateUatScenario;
use App\Actions\DecideUatCampaign;
use App\Actions\EnrollTrainingParticipant;
use App\Actions\RecordTrainingAssessment;
use App\Actions\RecordUatExecution;
use App\Actions\SubmitUatCampaign;
use App\Actions\TransitionRolloutWave;
use App\Actions\TransitionUatFinding;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\DecideUatCampaignRequest;
use App\Http\Requests\EnrollTrainingParticipantRequest;
use App\Http\Requests\RecordTrainingAssessmentRequest;
use App\Http\Requests\RecordUatExecutionRequest;
use App\Http\Requests\StoreRolloutWaveRequest;
use App\Http\Requests\StoreTrainingCohortRequest;
use App\Http\Requests\StoreUatCampaignRequest;
use App\Http\Requests\StoreUatScenarioRequest;
use App\Http\Requests\SubmitUatCampaignRequest;
use App\Http\Requests\TransitionRolloutWaveRequest;
use App\Http\Requests\TransitionUatFindingRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\RolloutWave;
use App\Models\TrainingAssessment;
use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
use App\Models\UatAcceptance;
use App\Models\UatCampaign;
use App\Models\UatExecution;
use App\Models\UatFinding;
use App\Models\UatScenario;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChangeReadinessController extends Controller
{
    public function __construct(private ProgrammeCountyScope $countyScope, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewChangeReadiness->value);
        $user = $this->user($request);
        $countyIds = $this->countyScope->query($user)->pluck('id');
        $national = $user->programmeRole()->hasNationalScope();
        $release = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($release?->snapshot['counties'] ?? [])->pluck('id')->filter()->all();
        $cohorts = TrainingCohort::query()->with(['wave:id,code,name,status', 'county:id,name,code,logo_path', 'referenceDataRelease:id,version,effective_from,checksum', 'facilitator:id,name', 'participants.user:id,name', 'participants.county:id,name,code,logo_path', 'participants.assessments.assessor:id,name'])->withCount(['participants', 'participants as completed_count' => fn (Builder $query) => $query->whereNotNull('completed_at')])->when(! $national, fn (Builder $query) => $query->whereIn('county_id', $countyIds))->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('starts_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('starts_at', '<=', $request->date('to')))->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = $request->string('search')->trim();
            $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhere('audience_role', 'ilike', "%{$search}%"));
        })->latest('starts_at')->paginate($request->integer('per_page', 10))->withQueryString();
        $waves = RolloutWave::query()->with(['counties:id,name,code,logo_path', 'referenceDataRelease:id,version,effective_from,checksum', 'creator:id,name', 'approver:id,name'])->withCount(['cohorts', 'cohorts as completed_participants_count' => fn (Builder $query) => $query->join('training_participants', 'training_cohorts.id', '=', 'training_participants.training_cohort_id')->whereNotNull('training_participants.completed_at')])->when(! $national, fn (Builder $query) => $query->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyIds)))->latest('starts_on')->get();
        $campaigns = UatCampaign::query()
            ->with([
                'referenceDataRelease:id,version,effective_from,checksum',
                'creator:id,name',
                'counties:id,name,code,logo_path',
                'scenarios.creator:id,name',
                'scenarios.executions.county:id,name,code,logo_path',
                'scenarios.executions.tester:id,name',
                'scenarios.executions.findings.owner:id,name',
                'scenarios.executions.findings.resolver:id,name',
                'scenarios.executions.findings.verifier:id,name',
                'acceptances.submitter:id,name',
                'acceptances.decisionMaker:id,name',
            ])
            ->when(! $national, fn (Builder $query) => $query->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyIds)))
            ->when($request->filled('uat_county_id'), fn (Builder $query) => $query->whereHas('counties', fn (Builder $query) => $query->whereKey($request->string('uat_county_id'))))
            ->when($request->filled('uat_status'), fn (Builder $query) => $query->where('status', $request->string('uat_status')))
            ->when($request->filled('uat_from'), fn (Builder $query) => $query->whereDate('starts_on', '>=', $request->date('uat_from')))
            ->when($request->filled('uat_to'), fn (Builder $query) => $query->whereDate('ends_on', '<=', $request->date('uat_to')))
            ->when($request->filled('uat_search'), function (Builder $query) use ($request): void {
                $search = $request->string('uat_search')->trim();
                $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhere('objective', 'ilike', "%{$search}%"));
            })
            ->latest('starts_on')
            ->paginate($request->integer('uat_per_page', 10), ['*'], 'uat_page')
            ->withQueryString();

        $availableUsers = User::query()->whereNull('access_revoked_at')->when(! $national, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereIn('county_id', $countyIds)->orWhereHas('assignedCounties', fn (Builder $query) => $query->whereIn('counties.id', $countyIds))))->orderBy('name')->get(['id', 'name']);

        return Inertia::render('change-readiness/index', ['waves' => $waves->map(fn (RolloutWave $wave): array => $this->wavePayload($wave))->values(), 'cohorts' => $cohorts->through(fn (TrainingCohort $cohort): array => $this->cohortPayload($cohort)), 'uatCampaigns' => $campaigns->through(fn (UatCampaign $campaign): array => $this->uatCampaignPayload($campaign)), 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'county_id', 'per_page', 'uat_from', 'uat_to', 'uat_search', 'uat_status', 'uat_county_id', 'uat_per_page']), 'catalogue' => $release === null ? ['available' => false] : ['available' => true, 'version' => $release->version, 'effectiveFrom' => $release->effective_from?->toDateString(), 'checksum' => $release->checksum], 'options' => ['counties' => $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get()->map->identityCell()->values(), 'users' => $availableUsers, 'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()])], 'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageChangeReadiness->value), 'recordEvidence' => $user->can(ProgrammePermission::RecordTrainingEvidence->value), 'recordUatEvidence' => $user->can(ProgrammePermission::RecordUatEvidence->value), 'approve' => $user->can(ProgrammePermission::ApproveRolloutReadiness->value)], 'uatCopy' => __('change-readiness.uat')]);
    }

    public function storeWave(StoreRolloutWaveRequest $request, CreateRolloutWave $action): RedirectResponse
    {
        $wave = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Rollout wave {$wave->code} created as a plan.");
    }

    public function storeCohort(StoreTrainingCohortRequest $request, CreateTrainingCohort $action): RedirectResponse
    {
        $cohort = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Training cohort {$cohort->code} planned.");
    }

    public function enroll(EnrollTrainingParticipantRequest $request, EnrollTrainingParticipant $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->validated());

        return back()->with('success', 'Participant registered; attendance and competency remain unverified.');
    }

    public function assess(RecordTrainingAssessmentRequest $request, TrainingParticipant $participant, RecordTrainingAssessment $action): RedirectResponse
    {
        $action->handle($participant, $this->user($request), $request->validated());

        return back()->with('success', 'Attendance and competency evidence recorded.');
    }

    public function approve(TransitionRolloutWaveRequest $request, RolloutWave $wave, TransitionRolloutWave $action): RedirectResponse
    {
        $action->handle($wave, $this->user($request), $request->validated());

        return back()->with('success', 'Rollout readiness independently approved.');
    }

    public function storeUatCampaign(StoreUatCampaignRequest $request, CreateUatCampaign $action): RedirectResponse
    {
        $campaign = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('change-readiness.messages.uat_campaign_created', ['code' => $campaign->code]));
    }

    public function storeUatScenario(StoreUatScenarioRequest $request, UatCampaign $campaign, CreateUatScenario $action): RedirectResponse
    {
        $scenario = $action->handle($campaign, $this->user($request), $request->validated());

        return back()->with('success', __('change-readiness.messages.uat_scenario_created', ['code' => $scenario->code]));
    }

    public function recordUatExecution(RecordUatExecutionRequest $request, UatScenario $scenario, RecordUatExecution $action): RedirectResponse
    {
        $execution = $action->handle($scenario, $this->user($request), $request->validated());

        return back()->with('success', __('change-readiness.messages.uat_execution_recorded', ['outcome' => $execution->outcome]));
    }

    public function transitionUatFinding(TransitionUatFindingRequest $request, UatFinding $finding, TransitionUatFinding $action): RedirectResponse
    {
        $finding = $action->handle($finding, $this->user($request), $request->validated());

        return back()->with('success', __('change-readiness.messages.uat_finding_transitioned', ['status' => $finding->status]));
    }

    public function submitUatCampaign(SubmitUatCampaignRequest $request, UatCampaign $campaign, SubmitUatCampaign $action): RedirectResponse
    {
        $action->handle($campaign, $this->user($request));

        return back()->with('success', __('change-readiness.messages.uat_campaign_submitted'));
    }

    public function decideUatCampaign(DecideUatCampaignRequest $request, UatAcceptance $acceptance, DecideUatCampaign $action): RedirectResponse
    {
        $acceptance = $action->handle($acceptance, $this->user($request), $request->validated());

        return back()->with('success', __('change-readiness.messages.uat_campaign_decided', ['decision' => $acceptance->decision]));
    }

    /** @return array<string, mixed> */
    private function wavePayload(RolloutWave $wave): array
    {
        return ['id' => $wave->id, 'referenceData' => $wave->referenceDataRelease === null ? null : ['version' => $wave->referenceDataRelease->version, 'effectiveFrom' => $wave->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $wave->referenceDataRelease->checksum], 'code' => $wave->code, 'name' => $wave->name, 'objective' => $wave->objective, 'startsOn' => $wave->starts_on->toDateString(), 'endsOn' => $wave->ends_on->toDateString(), 'plannedParticipants' => $wave->planned_participants, 'completedParticipants' => $wave->completed_participants_count, 'status' => $wave->status, 'entryCriteria' => $wave->entry_criteria, 'supportChannels' => $wave->support_channels, 'helpDeskRehearsed' => $wave->help_desk_rehearsed, 'trainingMaterialsApproved' => $wave->training_materials_approved, 'readinessNotes' => $wave->readiness_notes, 'approvedAt' => $wave->approved_at?->toIso8601String(), 'creator' => $wave->creator->name, 'approver' => $wave->approver?->name, 'counties' => $wave->counties->map(fn (County $county): array => $county->identityCell())->values()->all(), 'cohortCount' => $wave->cohorts_count];
    }

    /** @return array<string, mixed> */
    private function cohortPayload(TrainingCohort $cohort): array
    {
        $participants = $cohort->participants->map(function (TrainingParticipant $participant): array {
            return [
                'id' => $participant->id,
                'reference' => $participant->participant_reference,
                'name' => $participant->user_id ? $participant->user->name : 'External participant',
                'county' => $participant->county?->identityCell(),
                'roleTitle' => $participant->role_title,
                'attendedHours' => $participant->attended_hours,
                'attendanceStatus' => $participant->attendance_status,
                'competencyStatus' => $participant->competency_status,
                'completedAt' => $participant->completed_at?->toIso8601String(),
                'assessments' => $participant->assessments->map(fn (TrainingAssessment $assessment): array => ['type' => $assessment->assessment_type, 'score' => $assessment->score, 'outcome' => $assessment->outcome, 'feedback' => $assessment->feedback, 'evidenceReferences' => $assessment->evidence_references, 'assessor' => $assessment->assessor->name, 'assessedAt' => $assessment->assessed_at->toIso8601String()])->values()->all(),
            ];
        })->values()->all();

        return ['id' => $cohort->id, 'referenceData' => $cohort->referenceDataRelease === null ? null : ['version' => $cohort->referenceDataRelease->version, 'effectiveFrom' => $cohort->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $cohort->referenceDataRelease->checksum], 'wave' => ['id' => $cohort->wave->id, 'code' => $cohort->wave->code, 'name' => $cohort->wave->name], 'code' => $cohort->code, 'name' => $cohort->name, 'county' => $cohort->county?->identityCell(), 'audienceRole' => $cohort->audience_role, 'deliveryMode' => $cohort->delivery_mode, 'language' => $cohort->language, 'venue' => $cohort->venue, 'seatCapacity' => $cohort->seat_capacity, 'participantCount' => $cohort->participants_count, 'completedCount' => $cohort->completed_count, 'minimumAttendanceHours' => $cohort->minimum_attendance_hours, 'passingScore' => $cohort->passing_score, 'startsAt' => $cohort->starts_at->toIso8601String(), 'endsAt' => $cohort->ends_at->toIso8601String(), 'status' => $cohort->status, 'facilitator' => $cohort->facilitator?->name, 'participants' => $participants];
    }

    /** @return array<string, mixed> */
    private function uatCampaignPayload(UatCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'code' => $campaign->code,
            'name' => $campaign->name,
            'objective' => $campaign->objective,
            'environment' => $campaign->environment,
            'startsOn' => $campaign->starts_on->toDateString(),
            'endsOn' => $campaign->ends_on->toDateString(),
            'status' => $campaign->status,
            'acceptanceCriteria' => $campaign->acceptance_criteria,
            'requiredRoles' => $campaign->required_roles,
            'minimumCounties' => $campaign->minimum_counties,
            'creator' => $campaign->creator->name,
            'referenceData' => ['version' => $campaign->referenceDataRelease->version, 'effectiveFrom' => $campaign->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $campaign->referenceDataRelease->checksum],
            'counties' => $campaign->counties->map(fn (County $county): array => [...$county->identityCell(), 'participationStatus' => $county->pivot?->participation_status])->values()->all(),
            'scenarios' => $campaign->scenarios->map(fn (UatScenario $scenario): array => $this->uatScenarioPayload($scenario))->values()->all(),
            'acceptances' => $campaign->acceptances->sortByDesc('submitted_at')->map(fn (UatAcceptance $acceptance): array => ['id' => $acceptance->id, 'decision' => $acceptance->decision, 'criteriaSnapshot' => $acceptance->criteria_snapshot, 'coverageSnapshot' => $acceptance->coverage_snapshot, 'openFindingsCount' => $acceptance->open_findings_count, 'checksum' => $acceptance->checksum, 'decisionReason' => $acceptance->decision_reason, 'submitter' => $acceptance->submitter->name, 'decisionMaker' => $acceptance->decisionMaker?->name, 'submittedAt' => $acceptance->submitted_at->toIso8601String(), 'decidedAt' => $acceptance->decided_at?->toIso8601String()])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function uatScenarioPayload(UatScenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'code' => $scenario->code,
            'module' => $scenario->module,
            'title' => $scenario->title,
            'actorRole' => $scenario->actor_role,
            'priority' => $scenario->priority,
            'journey' => $scenario->journey,
            'preconditions' => $scenario->preconditions,
            'steps' => $scenario->steps,
            'expectedResult' => $scenario->expected_result,
            'accessibilityNeeds' => $scenario->accessibility_needs,
            'lowConnectivityVariant' => $scenario->low_connectivity_variant,
            'required' => $scenario->required,
            'status' => $scenario->status,
            'executions' => $scenario->executions->sortByDesc('completed_at')->map(fn (UatExecution $execution): array => ['id' => $execution->id, 'county' => $execution->county?->identityCell(), 'tester' => $execution->tester->name, 'environment' => $execution->environment, 'outcome' => $execution->outcome, 'actualResult' => $execution->actual_result, 'evidenceReferences' => $execution->evidence_references, 'startedAt' => $execution->started_at->toIso8601String(), 'completedAt' => $execution->completed_at->toIso8601String(), 'checksum' => $execution->checksum, 'findings' => $execution->findings->map(fn (UatFinding $finding): array => ['id' => $finding->id, 'severity' => $finding->severity, 'title' => $finding->title, 'description' => $finding->description, 'status' => $finding->status, 'dueOn' => $finding->due_on->toDateString(), 'resolution' => $finding->resolution, 'owner' => $finding->owner->name, 'resolver' => $finding->resolver?->name, 'verifier' => $finding->verifier?->name])->values()->all()])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
