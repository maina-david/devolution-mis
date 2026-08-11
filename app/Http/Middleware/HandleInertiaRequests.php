<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\AssessmentCycle;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'role' => $user->programmeRole()->name,
                    'role_label' => $user->programmeRole()->label(),
                    'permissions' => $user->programmePermissionValues(),
                    'county_identity' => $this->countyIdentity($user),
                    'avatar' => $user->profile_photo_path ? route('profile.photo', ['v' => $user->profile_photo_checksum]) : null,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'assessmentCycles' => fn () => $user
                ? AssessmentCycle::query()
                    ->orderByDesc('period_start')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (AssessmentCycle $cycle): array => [
                        'id' => $cycle->id,
                        'name' => "{$cycle->name} ({$cycle->code})",
                    ])
                    ->values()
                    ->all()
                : [],
            'notificationSummary' => fn () => [
                'unread' => $user?->unreadNotifications()->count() ?? 0,
                'recent' => $user?->notifications()->latest()->limit(5)->get()->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'title' => (string) ($notification->data['title'] ?? 'Notification'),
                    'message' => (string) ($notification->data['message'] ?? ''),
                    'category' => (string) ($notification->data['category'] ?? 'general'),
                    'url' => is_string($notification->data['url'] ?? null) ? $notification->data['url'] : null,
                    'readAt' => $notification->read_at?->toISOString(),
                    'createdAt' => $notification->created_at?->toISOString(),
                ])->values()->all() ?? [],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function countyIdentity(User $user): ?array
    {
        if (! in_array($user->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin], true)) {
            return null;
        }

        return $user->county()->first()?->identityCell();
    }
}
