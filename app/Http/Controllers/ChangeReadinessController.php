<?php

namespace App\Http\Controllers;

use App\Actions\CreateRolloutWave;
use App\Actions\CreateTrainingCohort;
use App\Actions\EnrollTrainingParticipant;
use App\Actions\RecordTrainingAssessment;
use App\Actions\TransitionRolloutWave;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\EnrollTrainingParticipantRequest;
use App\Http\Requests\RecordTrainingAssessmentRequest;
use App\Http\Requests\StoreRolloutWaveRequest;
use App\Http\Requests\StoreTrainingCohortRequest;
use App\Http\Requests\TransitionRolloutWaveRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\RolloutWave;
use App\Models\TrainingAssessment;
use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
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

        return Inertia::render('change-readiness/index', ['waves' => $waves->map(fn (RolloutWave $wave): array => $this->wavePayload($wave))->values(), 'cohorts' => $cohorts->through(fn (TrainingCohort $cohort): array => $this->cohortPayload($cohort)), 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'county_id', 'per_page']), 'catalogue' => $release === null ? ['available' => false] : ['available' => true, 'version' => $release->version, 'effectiveFrom' => $release->effective_from?->toDateString(), 'checksum' => $release->checksum], 'options' => ['counties' => $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get()->map->identityCell()->values(), 'users' => User::query()->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name']), 'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()])], 'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageChangeReadiness->value), 'recordEvidence' => $user->can(ProgrammePermission::RecordTrainingEvidence->value), 'approve' => $user->can(ProgrammePermission::ApproveRolloutReadiness->value)]]);
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
