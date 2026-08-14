<?php

namespace App\Services;

use App\Models\IgrResolution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class IgrResolutionAccess
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /** @return Builder<IgrResolution> */
    public function visibleTo(User $user): Builder
    {
        return IgrResolution::query()->when(
            ! $user->programmeRole()->hasNationalScope(),
            fn (Builder $query) => $query->whereHas(
                'assignments',
                fn (Builder $assignments) => $assignments
                    ->where('user_id', $user->id)
                    ->orWhereIn('county_id', $this->countyScope->query($user)->select('id')),
            ),
        );
    }

    public function allows(User $user, IgrResolution $resolution): bool
    {
        return $this->visibleTo($user)->whereKey($resolution)->exists();
    }
}
