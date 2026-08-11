<?php

namespace App\Services;

use App\Models\IgrResolution;
use App\Models\IgrResolutionGap;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class IgrGapScope
{
    /** @var array<string, int> */
    private const SEVERITY_RANK = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /** @return Builder<IgrResolutionGap> */
    public function visibleTo(User $user): Builder
    {
        return $this->apply(IgrResolutionGap::query(), $user);
    }

    /**
     * @param  Builder<IgrResolutionGap>  $query
     * @return Builder<IgrResolutionGap>
     */
    public function apply(Builder $query, User $user): Builder
    {
        $hasNationalScope = $user->programmeRole()->hasNationalScope();

        return $query
            ->whereHas('resolution', fn (Builder $query) => $query->when(! $hasNationalScope, fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignments) => $assignments
                ->where('user_id', $user->id)
                ->orWhereIn('county_id', $this->countyScope->query($user)->select('id')))))
            ->when(! $hasNationalScope, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('county_id')
                ->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))));
    }

    public function activeHeadline(IgrResolution $resolution): ?string
    {
        return $resolution->gaps
            ->where('status', '!=', 'accepted')
            ->sortByDesc(fn (IgrResolutionGap $gap): int => self::SEVERITY_RANK[$gap->severity] ?? 0)
            ->first()?->title;
    }
}
