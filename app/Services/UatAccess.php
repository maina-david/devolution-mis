<?php

namespace App\Services;

use App\Models\UatCampaign;
use App\Models\User;

class UatAccess
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    public function canView(User $user, UatCampaign $campaign): bool
    {
        if ($user->programmeRole()->hasNationalScope()) {
            return true;
        }

        return $campaign->counties()
            ->whereIn('counties.id', $this->countyScope->query($user)->select('counties.id'))
            ->exists();
    }

    public function authorize(User $user, UatCampaign $campaign): void
    {
        abort_unless($this->canView($user, $campaign), 403, __('change-readiness.uat_errors.campaign_scope'));
    }
}
