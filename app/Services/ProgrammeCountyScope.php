<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProgrammeCountyScope
{
    public function __construct(private DelegatedAccessResolver $delegatedAccess) {}

    /** @return Builder<County> */
    public function query(User $user): Builder
    {
        return County::query()
            ->when(! $user->programmeRole()->hasNationalScope() && ! $this->delegatedAccess->hasNationalScope($user), function (Builder $query) use ($user): void {
                $delegatedCountyIds = $this->delegatedAccess->countyIds($user);
                if (in_array($user->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin])) {
                    $query->whereIn('id', array_values(array_unique(array_filter([$user->county_id, ...$delegatedCountyIds]))));

                    return;
                }

                $query->where(fn (Builder $scope) => $scope->whereIn('id', $delegatedCountyIds)->orWhereHas('assignedUsers', fn (Builder $assigned) => $assigned->whereKey($user)));
            });
    }
}
