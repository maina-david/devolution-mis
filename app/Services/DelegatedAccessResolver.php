<?php

namespace App\Services;

use App\Models\AccessDelegation;
use App\Models\User;
use Illuminate\Support\Collection;

class DelegatedAccessResolver
{
    /** @var array<string, Collection<int, AccessDelegation>> */
    private array $activeDelegationsByUser = [];

    public function allows(User $user, string $ability): bool
    {
        if ($user->access_revoked_at !== null) {
            return false;
        }

        return $this->activeFor($user)->contains(fn (AccessDelegation $delegation): bool => in_array($ability, $delegation->permission_scope, true));
    }

    public function allowsCounty(User $user, string $countyId): bool
    {
        return $this->hasNationalScope($user) || in_array($countyId, $this->countyIds($user), true);
    }

    public function hasNationalScope(User $user): bool
    {
        return $this->activeFor($user)->contains(fn (AccessDelegation $delegation): bool => $delegation->scope_type === 'national');
    }

    /** @return list<string> */
    public function countyIds(User $user): array
    {
        return array_values($this->activeFor($user)->flatMap(fn (AccessDelegation $delegation) => collect($delegation->county_scope_snapshot)->pluck('id'))->filter(fn (mixed $countyId): bool => is_string($countyId))->unique()->values()->all());
    }

    /** @return list<string> */
    public function permissionValues(User $user): array
    {
        return array_values($this->activeFor($user)->flatMap(fn (AccessDelegation $delegation) => $delegation->permission_scope)->unique()->sort()->values()->all());
    }

    public function forget(string|User $user): void
    {
        unset($this->activeDelegationsByUser[$user instanceof User ? $user->id : $user]);
    }

    /** @return Collection<int, AccessDelegation> */
    private function activeFor(User $user): Collection
    {
        return $this->activeDelegationsByUser[$user->id] ??= AccessDelegation::query()
            ->where('beneficiary_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->get();
    }
}
