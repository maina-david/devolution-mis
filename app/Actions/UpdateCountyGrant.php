<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\CountyGrant;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Notification;

class UpdateCountyGrant
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{allocated_amount: int|float|string, disbursed_amount: int|float|string, status: string} $data */
    public function handle(CountyGrant $grant, array $data, User $actor): CountyGrant
    {
        $grant->update($data);
        $recipients = User::query()->where('county_id', $grant->county_id)->orWhere(fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', UserRole::DevelopmentPartner->value))->whereHas('assignedCounties', fn ($counties) => $counties->whereKey($grant->county_id)))->get();
        Notification::send($recipients, new ProgrammeAlert('Grant status updated', "{$grant->programme} for {$grant->county->name} is now {$grant->status}.", 'grant'));
        $this->auditLogger->record($actor, $grant, 'grant.updated', "Grant status changed to {$grant->status}.", $grant->county_id, $data);

        return $grant;
    }
}
