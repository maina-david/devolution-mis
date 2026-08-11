<?php

namespace App\Policies;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;

class CountyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(ProgrammePermission::ViewCountyData->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, County $county): bool
    {
        return $user->can(ProgrammePermission::ViewCountyData->value)
            && $user->canAccessCounty($county);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::DevolutionAdmin->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, County $county): bool
    {
        return $user->hasRole(UserRole::DevolutionAdmin->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, County $county): bool
    {
        return $user->hasRole(UserRole::PlatformAdmin->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, County $county): bool
    {
        return $user->hasRole(UserRole::PlatformAdmin->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, County $county): bool
    {
        return $user->hasRole(UserRole::PlatformAdmin->value);
    }
}
