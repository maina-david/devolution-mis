<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AssessmentCycle;
use App\Models\County;
use App\Models\User;
use App\Services\CountyDetailData;
use App\Support\WorkspaceFilters;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CountyController extends Controller
{
    public function __invoke(WorkspaceIndexRequest $request, string $currentTeam, County $county, CountyDetailData $countyDetailData): Response
    {
        Gate::authorize(ProgrammePermission::ViewCountyData->value);
        $user = $this->user($request);
        abort_unless($user->canAccessCounty($county), 403);

        return Inertia::render('counties/show', [
            ...$countyDetailData->for($county, WorkspaceFilters::fromRequest($request)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page', 'cycle_id']),
            'cycles' => AssessmentCycle::query()
                ->orderByDesc('period_start')
                ->get(['id', 'name', 'code'])
                ->map(fn (AssessmentCycle $cycle): array => [
                    'id' => $cycle->id,
                    'name' => "{$cycle->name} ({$cycle->code})",
                ]),
            'capabilities' => [
                'submit' => $user->can(ProgrammePermission::SubmitAssessment->value),
                'review' => $user->can(ProgrammePermission::ReviewAssessment->value),
                'score' => $user->can(ProgrammePermission::ScoreAssessment->value),
                'approve' => $user->can(ProgrammePermission::ApproveAssessment->value),
                'upload' => $user->can(ProgrammePermission::UploadEvidence->value),
                'verify' => $user->can(ProgrammePermission::ReviewAssessment->value),
                'manageGrants' => $user->can(ProgrammePermission::ManageGrants->value),
            ],
        ]);
    }

    private function user(WorkspaceIndexRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
