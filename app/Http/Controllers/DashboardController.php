<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Services\DashboardData;
use App\Support\WorkspaceFilters;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(WorkspaceIndexRequest $request, DashboardData $dashboardData): Response
    {
        Gate::authorize(ProgrammePermission::ViewDashboard->value);

        return Inertia::render('dashboard', [
            ...$dashboardData->for($request->user(), WorkspaceFilters::fromRequest($request)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'cycle_id']),
        ]);
    }
}
