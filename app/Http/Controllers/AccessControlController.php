<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateUserDirectPermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

class AccessControlController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement($request);
        $search = $request->string('search')->trim()->toString();
        $permissions = Permission::query()->orderBy('name')->get();
        $roles = Role::query()->with('permissions:id,name')->withCount('users')->orderBy('name')->get();
        $users = User::query()->with(['roles:id,name', 'permissions:id,name'])
            ->when($search, fn (Builder $query, string $value) => $query->where(fn (Builder $nested) => $nested->where('name', 'ilike', "%{$value}%")->orWhere('email', 'ilike', "%{$value}%")))
            ->orderBy('name')->paginate(max(10, min(100, $request->integer('per_page', 10))))->withQueryString();
        $userRows = $users->getCollection()->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?: 'No role',
            'rolePermissions' => $user->getPermissionsViaRoles()->pluck('name')->sort()->values(),
            'directPermissions' => $user->getDirectPermissions()->pluck('name')->sort()->values(),
            'effectivePermissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
        ])->values();

        return Inertia::render('access-control/index', [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => UserRole::tryFrom($role->name)?->label() ?? str($role->name)->headline()->toString(),
                'userCount' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
            ])->values(),
            'permissions' => $permissions->map(fn (Permission $permission): array => [
                'value' => $permission->name,
                'label' => ProgrammePermission::tryFrom($permission->name)?->label() ?? str($permission->name)->headline()->toString(),
                'group' => str($permission->name)->before(':')->headline()->toString(),
            ])->values(),
            'users' => [
                'data' => $userRows,
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'links' => $users->linkCollection(),
            ],
            'filters' => ['search' => $search, 'per_page' => $request->integer('per_page', 10)],
        ]);
    }

    public function updateRole(UpdateRolePermissionsRequest $request, string $currentTeam, string $role, AuditLogger $auditLogger): RedirectResponse
    {
        $actor = $this->user($request);
        $roleModel = Role::query()->where('name', $role)->firstOrFail();
        $before = $roleModel->permissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($request, $roleModel, $actor, $before, $auditLogger): void {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($roleModel->id);
            $lockedRole->syncPermissions($request->permissionNames());
            $auditLogger->record($actor, $lockedRole, 'access.role_permissions.updated', 'Role permission matrix updated.', metadata: [
                'before' => $before,
                'after' => collect($request->permissionNames())->sort()->values()->all(),
                'reason' => $request->string('reason')->toString(),
            ]);
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role permission matrix updated.']);

        return back();
    }

    public function updateUser(UpdateUserDirectPermissionsRequest $request, string $currentTeam, User $programmeUser, AuditLogger $auditLogger): RedirectResponse
    {
        $actor = $this->user($request);
        $before = $programmeUser->getDirectPermissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($request, $programmeUser, $actor, $before, $auditLogger): void {
            $target = User::query()->lockForUpdate()->findOrFail($programmeUser->id);
            $target->syncPermissions($request->permissionNames());
            $auditLogger->record($actor, $target, 'access.direct_permissions.updated', 'Direct user permission exceptions updated.', $target->county_id, [
                'before' => $before,
                'after' => collect($request->permissionNames())->sort()->values()->all(),
                'reason' => $request->string('reason')->toString(),
            ]);
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Direct permission exceptions updated.']);

        return back();
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($this->user($request)->can(ProgrammePermission::ManageUserAccess->value), 403);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
