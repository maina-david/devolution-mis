<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditLogger;

class DeactivateProgrammeUser
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $target, User $actor): void
    {
        abort_unless($actor->can(ProgrammePermission::ManageCountyUsers->value) || $actor->can(ProgrammePermission::ManageUserAccess->value), 403);
        abort_if($actor->is($target), 409, 'You cannot deactivate your own account.');
        abort_unless($this->allows($actor, $target), 403);

        $this->auditLogger->record($actor, $target, 'access.deactivated', "Programme access deactivated for {$target->email}.", $target->county_id, ['role' => $target->programmeRole()->value]);
        $target->delete();
    }

    public function allows(User $actor, User $target): bool
    {
        if ($actor->can(ProgrammePermission::ManageUserAccess->value)) {
            return true;
        }

        if ($actor->programmeRole() === UserRole::CountyAdmin) {
            return $target->county_id === $actor->county_id && $target->programmeRole() === UserRole::CountyOfficial;
        }

        return in_array($target->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin]);
    }
}
