<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserPageView;
use App\Services\UserActivityTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(private UserActivityTracker $tracker) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        if (! $user instanceof User || ! $request->route()?->getName()) {
            return $response;
        }

        $completed = ! $response->isClientError() && ! $response->isServerError();
        $isPageView = $completed && $request->isMethod('GET') && ($response->headers->get('X-Inertia') === 'true' || str_contains((string) $response->headers->get('Content-Type'), 'text/html'));
        if (! $isPageView) {
            return $response;
        }

        $routeName = (string) $request->route()->getName();
        $action = 'page.viewed';
        $pageTitle = str($routeName)->beforeLast('.')->replace(['-', '.'], ' ')->title()->toString();
        $activitySession = $this->tracker->touchExisting($request, $user, $action, $pageTitle);
        UserPageView::create(['user_id' => $user->id, 'user_activity_session_id' => $activitySession?->id, 'team_id' => $user->current_team_id, 'route_name' => $routeName, 'path' => '/'.ltrim($request->path(), '/'), 'page_title' => $pageTitle, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'viewed_at' => now()]);

        return $response;
    }
}
