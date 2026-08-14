<?php

namespace App\Actions;

use App\Models\LearningCohort;
use App\Models\LearningCohortMembership;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddLearningCohortMember
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(LearningCohort $cohort, LearningEnrollment $enrollment, User $actor): LearningCohortMembership
    {
        return DB::transaction(function () use ($cohort, $enrollment, $actor): LearningCohortMembership {
            $lockedCohort = LearningCohort::query()->whereKey($cohort->id)->lockForUpdate()->firstOrFail();
            $lockedEnrollment = LearningEnrollment::query()->with('user')->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedCohort->status, ['draft', 'open'], true), 409, __('learning.cohort.errors.membership_locked'));
            abort_unless($lockedCohort->learning_course_id === $lockedEnrollment->learning_course_id, 422, __('learning.cohort.errors.enrollment_course_mismatch'));
            abort_unless(in_array($lockedEnrollment->status, ['enrolled', 'in_progress'], true), 422, __('learning.cohort.errors.active_enrollment_required'));
            if ($lockedCohort->county_id !== null) {
                abort_unless($lockedEnrollment->county_id === $lockedCohort->county_id, 422, __('learning.cohort.errors.enrollment_county_scope'));
            }
            if (! $actor->programmeRole()->hasNationalScope()) {
                $hasCountyAccess = $lockedCohort->county_id !== null
                    && ($actor->county_id === $lockedCohort->county_id || $actor->assignedCounties()->whereKey($lockedCohort->county_id)->exists());
                abort_unless($hasCountyAccess && $lockedEnrollment->county_id === $lockedCohort->county_id, 403);
            }
            abort_if($lockedCohort->memberships()->count() >= $lockedCohort->capacity, 409, __('learning.cohort.errors.capacity_reached'));
            if ($lockedCohort->memberships()->where('learning_enrollment_id', $lockedEnrollment->id)->withTrashed()->exists()) {
                throw ValidationException::withMessages(['learning_enrollment_id' => __('learning.cohort.errors.membership_retained')]);
            }

            $membership = $lockedCohort->memberships()->create(['learning_enrollment_id' => $lockedEnrollment->id, 'added_by' => $actor->id, 'joined_at' => now()]);
            $this->auditLogger->record($actor, $membership, 'learning.cohort.member-added', __('learning.cohort.audit.member_added', ['learner' => $lockedEnrollment->user->name, 'cohort' => $lockedCohort->code]), $lockedCohort->county_id, ['learning_enrollment_id' => $lockedEnrollment->id]);

            return $membership;
        });
    }
}
