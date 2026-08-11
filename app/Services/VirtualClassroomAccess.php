<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Models\User;
use App\Models\VirtualClassroom;

class VirtualClassroomAccess
{
    public function canManageAttendance(User $user, VirtualClassroom $classroom): bool
    {
        if ($classroom->facilitator_id === $user->id) {
            return true;
        }

        if (! $user->can(ProgrammePermission::ManageLearning->value)) {
            return false;
        }

        $classroom->loadMissing('course.county');

        return $classroom->course->county === null
            ? $user->programmeRole()->hasNationalScope()
            : $user->canAccessCounty($classroom->course->county);
    }
}
