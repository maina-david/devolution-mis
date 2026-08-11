<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Support\WorkspaceFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(WorkspaceIndexRequest $request): Response
    {
        $filters = WorkspaceFilters::fromRequest($request);
        $notifications = $this->user($request)->notifications()
            ->when($filters->from, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters->to, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters->search !== '', fn ($query) => $query->where(fn ($query) => $query->whereRaw("LOWER(data->>'title') LIKE ?", ['%'.mb_strtolower($filters->search).'%'])->orWhereRaw("LOWER(data->>'message') LIKE ?", ['%'.mb_strtolower($filters->search).'%'])))
            ->paginate($filters->perPage)->withQueryString()->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'],
                'message' => $notification->data['message'],
                'category' => $notification->data['category'],
                'url' => $notification->data['url'],
                'readAt' => $notification->read_at?->toISOString(),
                'createdAt' => $notification->created_at?->toISOString(),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications->items(),
            'pagination' => ['currentPage' => $notifications->currentPage(), 'lastPage' => $notifications->lastPage(), 'total' => $notifications->total()],
            'filters' => $request->safe()->only(['from', 'to', 'search']),
        ]);
    }

    public function read(Request $request, string $currentTeam, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $this->user($request)->id && $notification->notifiable_type === User::class, 403);
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
