<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\TeamInvitation;
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

        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            ...$dashboardData->for($request->user(), WorkspaceFilters::fromRequest($request)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'cycle_id']),
        ]);
    }
}
