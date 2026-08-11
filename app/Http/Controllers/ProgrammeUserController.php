<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateProgrammeUser;
use App\Actions\GrantProgrammeAccess;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\StoreProgrammeUserRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AccessDelegation;
use App\Models\AccessReviewItem;
use App\Models\AuditEvent;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Models\UserPageView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeUserController extends Controller
{
    public function show(WorkspaceIndexRequest $request, string $currentTeam, User $programmeUser): Response
    {
        $actor = $this->user($request);
        $this->authorizeView($actor, $programmeUser);

        $programmeUser->load([
            'county:id,name,code,logo_path',
            'assignedCounties:id,name,code,logo_path',
            'roles:id,name',
            'teams:id,name',
        ])->loadCount('passkeys');

        $canViewActivity = $actor->can(ProgrammePermission::ViewUserActivity->value);
        $canViewAudit = $canViewActivity || $actor->can(ProgrammePermission::ViewAuditTrail->value);
        $canViewAccessGovernance = $actor->can(ProgrammePermission::ManageUserAccess->value);
        $perPage = $request->integer('per_page', 10);
        $search = $request->string('search')->trim()->toString();

        $sessions = $canViewActivity
            ? UserActivitySession::query()->where('user_id', $programmeUser->id)
                ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('logged_in_at', '>=', $request->date('from')))
                ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('logged_in_at', '<=', $request->date('to')))
                ->latest('logged_in_at')->paginate($perPage, pageName: 'session_page')->withQueryString()
            : null;
        $pageViews = $canViewActivity
            ? UserPageView::query()->where('user_id', $programmeUser->id)
                ->when($search, fn (Builder $query, string $value) => $query->where(fn (Builder $nested) => $nested->where('page_title', 'ilike', "%{$value}%")->orWhere('route_name', 'ilike', "%{$value}%")->orWhere('path', 'ilike', "%{$value}%")))
                ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('viewed_at', '>=', $request->date('from')))
                ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('viewed_at', '<=', $request->date('to')))
                ->latest('viewed_at')->paginate($perPage, pageName: 'view_page')->withQueryString()
            : null;
        $auditEvents = $canViewAudit
            ? AuditEvent::query()->with('actor:id,name')->where(function (Builder $query) use ($programmeUser): void {
                $query->where('actor_id', $programmeUser->id)
                    ->orWhere(fn (Builder $subject) => $subject->where('subject_type', $programmeUser->getMorphClass())->where('subject_id', $programmeUser->id));
            })->when($search, fn (Builder $query, string $value) => $query->where(fn (Builder $nested) => $nested->where('action', 'ilike', "%{$value}%")->orWhere('description', 'ilike', "%{$value}%")))
                ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
                ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
                ->latest('occurred_at')->paginate($perPage, pageName: 'event_page')->withQueryString()
            : null;

        $latestSession = UserActivitySession::query()->where('user_id', $programmeUser->id)->latest('last_seen_at')->first();

        return Inertia::render('programme-users/show', [
            'profile' => [
                'id' => $programmeUser->id,
                'name' => $programmeUser->name,
                'email' => $programmeUser->email,
                'role' => ['value' => $programmeUser->programmeRole()->value, 'label' => $programmeUser->programmeRole()->label()],
                'status' => $programmeUser->access_revoked_at ? 'revoked' : ($programmeUser->email_verified_at ? 'active' : 'pending_verification'),
                'homeCounty' => $programmeUser->county?->identityCell(),
                'assignedCounties' => $programmeUser->assignedCounties->map->identityCell()->values(),
                'permissions' => $programmeUser->programmePermissionValues(),
                'teams' => $programmeUser->teams->map(fn ($team): array => ['id' => $team->id, 'name' => $team->name])->values(),
                'emailVerifiedAt' => $programmeUser->email_verified_at?->toIso8601String(),
                'twoFactorEnabled' => $programmeUser->two_factor_confirmed_at !== null,
                'passkeyCount' => $programmeUser->passkeys_count,
                'accessRevokedAt' => $programmeUser->access_revoked_at?->toIso8601String(),
                'accessRevocationReason' => $programmeUser->access_revocation_reason,
                'createdAt' => $programmeUser->created_at?->toIso8601String(),
                'updatedAt' => $programmeUser->updated_at?->toIso8601String(),
            ],
            'summary' => [
                'sessionCount' => $canViewActivity ? UserActivitySession::query()->where('user_id', $programmeUser->id)->count() : null,
                'pageViewCount' => $canViewActivity ? UserPageView::query()->where('user_id', $programmeUser->id)->count() : null,
                'auditEventCount' => $canViewAudit ? AuditEvent::query()->where('actor_id', $programmeUser->id)->orWhere(fn (Builder $query) => $query->where('subject_type', $programmeUser->getMorphClass())->where('subject_id', $programmeUser->id))->count() : null,
                'lastSeenAt' => $canViewActivity ? $latestSession?->last_seen_at?->toIso8601String() : null,
                'currentPage' => $canViewActivity && $latestSession && $latestSession->logged_out_at === null && $latestSession->last_seen_at->gte(now()->subMinutes(5)) ? ($latestSession->current_page_title ?? $latestSession->current_path) : null,
            ],
            'sessions' => $sessions ? $this->table($sessions, fn (UserActivitySession $session): array => ['id' => $session->id, 'cells' => [$session->current_page_title ?? $session->current_path ?? '—', $session->ip_address ?? '—', $session->logged_in_at->toIso8601String(), $session->last_seen_at->toIso8601String(), $session->logged_out_at?->toIso8601String() ?? 'Online'], 'meta' => ['userAgent' => $session->user_agent]]) : null,
            'pageViews' => $pageViews ? $this->table($pageViews, fn (UserPageView $view): array => ['id' => $view->id, 'cells' => [$view->page_title, $view->route_name, $view->path, $view->ip_address ?? '—', $view->viewed_at->toIso8601String()]]) : null,
            'auditEvents' => $auditEvents ? $this->table($auditEvents, fn (AuditEvent $event): array => ['id' => $event->id, 'cells' => [$event->action, $event->description, $event->actor?->name ?? 'System', $event->metadata['request_method'] ?? '—', $event->ip_address ?? '—', $event->occurred_at?->toIso8601String() ?? '—']]) : null,
            'accessGovernance' => $canViewAccessGovernance ? [
                'lifecycleRequests' => IdentityLifecycleRequest::query()->where('user_id', $programmeUser->id)->latest()->limit(50)->get()->map(fn (IdentityLifecycleRequest $item): array => ['id' => $item->id, 'reference' => $item->source_evidence_reference, 'eventType' => $item->event_type, 'status' => $item->status, 'effectiveAt' => $item->effective_at->toIso8601String(), 'businessReason' => $item->business_reason]),
                'accessReviews' => AccessReviewItem::query()->with('campaign:id,reference,name')->where('user_id', $programmeUser->id)->latest()->limit(50)->get()->map(fn (AccessReviewItem $item): array => ['id' => $item->id, 'campaign' => $item->campaign->name, 'reference' => $item->campaign->reference, 'decision' => $item->decision, 'role' => $item->role_name, 'reviewedAt' => $item->reviewed_at?->toIso8601String(), 'rationale' => $item->rationale]),
                'delegations' => AccessDelegation::query()->where('beneficiary_id', $programmeUser->id)->latest()->limit(50)->get()->map(fn (AccessDelegation $item): array => ['id' => $item->id, 'reference' => $item->reference, 'accessType' => $item->access_type, 'status' => $item->status, 'startsAt' => $item->starts_at->toIso8601String(), 'expiresAt' => $item->expires_at->toIso8601String(), 'permissions' => $item->permission_scope]),
            ] : null,
            'capabilities' => ['viewActivity' => $canViewActivity, 'viewAudit' => $canViewAudit, 'viewAccessGovernance' => $canViewAccessGovernance],
            'filters' => $request->safe()->only(['from', 'to', 'search', 'per_page']),
        ]);
    }

    public function store(StoreProgrammeUserRequest $request, string $currentTeam, GrantProgrammeAccess $grantAccess): RedirectResponse
    {
        $grantAccess->handle($request->accessData(), $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User access granted and password setup requested.']);

        return back();
    }

    public function destroy(Request $request, string $currentTeam, User $programmeUser, DeactivateProgrammeUser $deactivate): RedirectResponse
    {
        $actor = $this->user($request);
        $deactivate->handle($programmeUser, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User access deactivated.']);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function authorizeView(User $actor, User $target): void
    {
        abort_unless($actor->can(ProgrammePermission::ManageCountyUsers->value) || $actor->can(ProgrammePermission::ManageUserAccess->value), 403);

        if ($actor->can(ProgrammePermission::ManageUserAccess->value)) {
            return;
        }

        if ($actor->programmeRole() === UserRole::CountyAdmin) {
            abort_unless($target->county_id === $actor->county_id, 403);

            return;
        }

        abort_unless(in_array($target->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin], true), 403);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<TModel>  $paginator
     * @param  callable(TModel): array<string, mixed>  $transform
     * @return array{rows: list<array<string, mixed>>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, pageName: string}}
     */
    private function table(LengthAwarePaginator $paginator, callable $transform): array
    {
        return [
            'rows' => $paginator->getCollection()->map($transform)->values()->all(),
            'pagination' => ['currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(), 'perPage' => $paginator->perPage(), 'total' => $paginator->total(), 'pageName' => $paginator->getPageName()],
        ];
    }
}
