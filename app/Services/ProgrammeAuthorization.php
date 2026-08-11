<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ProgrammeAuthorization
{
    public function ensureRole(UserRole $userRole): Role
    {
        $permissions = collect($userRole->permissions())
            ->map(fn (ProgrammePermission $permission) => Permission::findOrCreate($permission->value, 'web'));

        /** @var Role $role */
        $role = Role::findOrCreate($userRole->value, 'web');
        $role->syncPermissions($permissions);

        return $role;
    }

    public function assignRole(User $user, UserRole $userRole): void
    {
        DB::transaction(function () use ($user, $userRole): void {
            $user->syncRoles($this->ensureRole($userRole));

            if ($userRole->hasNationalScope()) {
                $user->forceFill(['county_id' => null])->save();
                $user->assignedCounties()->sync([]);
            }
        });
    }

    public function seedMatrix(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            $this->ensureRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
