<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Http\Requests\UserActivityIndexRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Models\UserPageView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserActivityController extends Controller
{
    public function __invoke(UserActivityIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewUserActivity->value);
        $userId = $request->validated('user_id');
        $sessionId = $request->validated('session_id');
        $search = $request->string('search')->trim()->toString();
        $perPage = $request->integer('per_page', 15);
        $onlineCutoff = now()->subMinutes(5);

        $activeSessions = UserActivitySession::query()
            ->with(['user:id,name,email,county_id', 'user.roles:id,name', 'user.county:id,name,code,logo_path'])
            ->whereNull('logged_out_at')->where('last_seen_at', '>=', $onlineCutoff)
            ->latest('last_seen_at')->get()->map(fn (UserActivitySession $session): array => $this->sessionData($session));

        $sessions = UserActivitySession::query()->with(['user:id,name,email,county_id', 'user.roles:id,name', 'user.county:id,name,code,logo_path'])
            ->when($userId, fn (Builder $query, string $id) => $query->where('user_id', $id))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('logged_in_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('logged_in_at', '<=', $request->date('to')))
            ->latest('logged_in_at')->paginate($perPage, pageName: 'session_page')->withQueryString();

        $events = AuditEvent::query()->with(['actor:id,name,email'])
            ->when($userId, fn (Builder $query, string $id) => $query->where('actor_id', $id))
            ->when($sessionId, fn (Builder $query, string $id) => $query->where('metadata->activity_session_id', $id))
            ->when($search, fn (Builder $query, string $value) => $query->where(fn (Builder $nested) => $nested->where('action', 'ilike', "%{$value}%")->orWhere('description', 'ilike', "%{$value}%")))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->latest('occurred_at')->paginate($perPage, pageName: 'event_page')->withQueryString();

        $pageViews = UserPageView::query()->with('user:id,name,email')
            ->when($userId, fn (Builder $query, string $id) => $query->where('user_id', $id))
            ->when($sessionId, fn (Builder $query, string $id) => $query->where('user_activity_session_id', $id))
            ->when($search, fn (Builder $query, string $value) => $query->where(fn (Builder $nested) => $nested->where('page_title', 'ilike', "%{$value}%")->orWhere('route_name', 'ilike', "%{$value}%")->orWhere('path', 'ilike', "%{$value}%")))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('viewed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('viewed_at', '<=', $request->date('to')))
            ->latest('viewed_at')->paginate($perPage, pageName: 'view_page')->withQueryString();

        return Inertia::render('user-activity/index', [
            'activeSessions' => $activeSessions,
            'sessions' => $sessions->through(fn (UserActivitySession $session): array => $this->sessionData($session)),
            'events' => $events->through(fn (AuditEvent $event): array => ['id' => $event->id, 'actor' => $event->actor_id === null ? 'System' : $event->actor->name, 'action' => $event->action, 'description' => $event->description, 'route' => $event->metadata['route_name'] ?? null, 'method' => $event->metadata['request_method'] ?? null, 'ipAddress' => $event->ip_address, 'occurredAt' => $event->occurred_at?->toIso8601String(), 'sessionId' => $event->metadata['activity_session_id'] ?? null]),
            'pageViews' => $pageViews->through(fn (UserPageView $view): array => ['id' => $view->id, 'user' => $view->user->name, 'pageTitle' => $view->page_title, 'route' => $view->route_name, 'path' => $view->path, 'ipAddress' => $view->ip_address, 'viewedAt' => $view->viewed_at->toIso8601String()]),
            'users' => User::query()->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email'])->map(fn (User $user): array => ['id' => $user->id, 'name' => "{$user->name} · {$user->email}"]),
            'filters' => ['userId' => $userId, 'sessionId' => $sessionId, 'search' => $search, 'from' => $request->validated('from'), 'to' => $request->validated('to')],
            'onlineWindowMinutes' => 5,
        ]);
    }

    /** @return array<string, mixed> */
    private function sessionData(UserActivitySession $session): array
    {
        return ['id' => $session->id, 'user' => ['id' => $session->user->id, 'name' => $session->user->name, 'email' => $session->user->email, 'role' => $session->user->programmeRole()->label(), 'county' => $session->user->county?->identityCell()], 'currentRoute' => $session->current_route, 'currentPath' => $session->current_path, 'currentPageTitle' => $session->current_page_title, 'lastMethod' => $session->last_method, 'lastAction' => $session->last_action, 'ipAddress' => $session->ip_address, 'userAgent' => $session->user_agent, 'loggedInAt' => $session->logged_in_at->toIso8601String(), 'lastSeenAt' => $session->last_seen_at->toIso8601String(), 'loggedOutAt' => $session->logged_out_at?->toIso8601String()];
    }
}
