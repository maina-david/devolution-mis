<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AssessmentCycle;
use App\Models\User;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeWorkspaceController extends Controller
{
    public function __construct(private ProgrammeWorkspaceData $workspaceData) {}

    public function counties(WorkspaceIndexRequest $request): Response
    {
        return $this->render($request, ProgrammePermission::ViewCountyData, 'counties');
    }

    public function assessments(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewCountyData->value);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->assessments($this->user($request), WorkspaceFilters::fromRequest($request)),
            'workspaceType' => 'assessments',
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page', 'cycle_id']),
            'cycles' => $this->cycleOptions(),
            'capabilities' => [
                'create' => $request->user()?->can(ProgrammePermission::ManageAssessmentConfiguration->value),
                'submit' => $request->user()?->can(ProgrammePermission::SubmitAssessment->value),
                'review' => $request->user()?->can(ProgrammePermission::ReviewAssessment->value),
                'score' => $request->user()?->can(ProgrammePermission::ScoreAssessment->value),
                'approve' => $request->user()?->can(ProgrammePermission::ApproveAssessment->value),
            ],
        ]);
    }

    public function evidence(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewCountyData->value);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->evidence($this->user($request), WorkspaceFilters::fromRequest($request)),
            'workspaceType' => 'evidence',
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page', 'cycle_id']),
            'cycles' => $this->cycleOptions(),
            'capabilities' => [
                'download' => true,
                'upload' => $request->user()?->can(ProgrammePermission::UploadEvidence->value),
                'verify' => $request->user()?->can(ProgrammePermission::ReviewAssessment->value),
                'manageRecords' => $request->user()?->can(ProgrammePermission::ManageRecords->value),
            ],
        ]);
    }

    public function grants(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewGrants->value);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->grants($this->user($request), WorkspaceFilters::fromRequest($request)),
            'workspaceType' => 'grants',
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
            'capabilities' => ['manage' => $request->user()?->can(ProgrammePermission::ManageGrants->value)],
        ]);
    }

    public function reports(WorkspaceIndexRequest $request): Response
    {
        return $this->render($request, ProgrammePermission::ViewNationalReports, 'reports');
    }

    public function users(WorkspaceIndexRequest $request): Response
    {
        $allowed = $request->user()?->can(ProgrammePermission::ManageCountyUsers->value)
            || $request->user()?->can(ProgrammePermission::ManageUserAccess->value);
        abort_unless($allowed, 403);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->users($this->user($request), WorkspaceFilters::fromRequest($request)),
            'workspaceType' => 'users',
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
            'capabilities' => [
                'manage' => true,
                'bulkImport' => $request->user()->can(ProgrammePermission::ManageUserAccess->value),
            ],
        ]);
    }

    public function audit(WorkspaceIndexRequest $request): Response
    {
        abort_unless(in_array($this->user($request)->programmeRole(), [UserRole::DevolutionAdmin, UserRole::PlatformAdmin], true), 403);

        return $this->render($request, ProgrammePermission::ViewAuditTrail, 'audit');
    }

    public function platform(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ConfigurePlatform->value);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->platform(WorkspaceFilters::fromRequest($request)),
            'workspaceType' => 'platform',
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
            'capabilities' => ['configure' => true],
        ]);
    }

    private function render(WorkspaceIndexRequest $request, ProgrammePermission $permission, string $workspace): Response
    {
        Gate::authorize($permission->value);

        return Inertia::render('programme/workspace', [
            'workspace' => $this->workspaceData->{$workspace}($this->user($request), WorkspaceFilters::fromRequest($request)),
            'workspaceType' => $workspace,
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
            'capabilities' => [],
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array<int, array{id: string, name: string}> */
    private function cycleOptions(): array
    {
        return AssessmentCycle::query()
            ->orderByDesc('period_start')
            ->get(['id', 'name', 'code'])
            ->map(fn (AssessmentCycle $cycle): array => [
                'id' => $cycle->id,
                'name' => "{$cycle->name} ({$cycle->code})",
            ])
            ->all();
    }
}
