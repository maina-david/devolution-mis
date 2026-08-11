<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketAccess
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /** @return Builder<SupportTicket> */
    public function query(User $user): Builder
    {
        abort_unless($user->can(ProgrammePermission::ViewSupportDesk->value), 403);

        return SupportTicket::query()->when(! $user->programmeRole()->hasNationalScope(), function (Builder $query) use ($user): void {
            $query->where(function (Builder $scope) use ($user): void {
                $scope->where('requester_id', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereIn('county_id', $this->countyScope->query($user)->select('id'));
            });
        });
    }

    public function allows(User $user, SupportTicket $ticket): bool
    {
        return $user->can(ProgrammePermission::ViewSupportDesk->value)
            && $this->query($user)->whereKey($ticket->id)->exists();
    }

    public function assertCounty(User $user, ?string $countyId): void
    {
        if ($countyId === null) {
            abort_unless($user->programmeRole()->hasNationalScope(), 403, 'Only national support users may submit a national ticket.');

            return;
        }

        abort_unless($this->countyScope->query($user)->whereKey($countyId)->exists(), 403, 'The selected county is outside your authorized scope.');
    }
}
