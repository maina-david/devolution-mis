<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivitySession;
use Illuminate\Http\Request;

class UserActivityTracker
{
    public function start(Request $request, User $user): UserActivitySession
    {
        $activitySession = UserActivitySession::query()->firstOrCreate(['session_fingerprint' => $this->fingerprint($request)], ['user_id' => $user->id, 'team_id' => $user->current_team_id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'logged_in_at' => now(), 'last_seen_at' => now()]);
        $request->session()->put('activity_session_id', $activitySession->id);

        return $activitySession;
    }

    public function touch(Request $request, User $user, string $action, ?string $pageTitle = null): UserActivitySession
    {
        $activitySession = $this->find($request, $user) ?? $this->start($request, $user);
        $activitySession->update(['team_id' => $user->current_team_id, 'current_route' => $request->route()?->getName(), 'current_path' => '/'.ltrim($request->path(), '/'), 'current_page_title' => $pageTitle, 'last_method' => $request->method(), 'last_action' => $action, 'last_seen_at' => now(), 'logged_out_at' => null]);

        return $activitySession;
    }

    public function touchExisting(Request $request, User $user, string $action, ?string $pageTitle = null): ?UserActivitySession
    {
        $activitySession = $this->find($request, $user);
        $activitySession?->update(['team_id' => $user->current_team_id, 'current_route' => $request->route()?->getName(), 'current_path' => '/'.ltrim($request->path(), '/'), 'current_page_title' => $pageTitle, 'last_method' => $request->method(), 'last_action' => $action, 'last_seen_at' => now(), 'logged_out_at' => null]);

        return $activitySession;
    }

    public function finish(Request $request, User $user): ?UserActivitySession
    {
        $activitySession = $this->find($request, $user);
        $activitySession?->update(['last_action' => 'auth.logout', 'last_seen_at' => now(), 'logged_out_at' => now()]);

        return $activitySession;
    }

    private function find(Request $request, User $user): ?UserActivitySession
    {
        $id = $request->session()->get('activity_session_id');

        if (! is_string($id)) {
            return null;
        }

        return UserActivitySession::query()->where('user_id', $user->id)->whereKey($id)->first();
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
