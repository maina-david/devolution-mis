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
            abort_unless(in_array($lockedCohort->status, ['draft', 'open'], true), 409, 'Membership is locked after cohort delivery starts.');
            abort_unless($lockedCohort->learning_course_id === $lockedEnrollment->learning_course_id, 422, 'The learner enrollment belongs to a different course.');
            abort_unless(in_array($lockedEnrollment->status, ['enrolled', 'in_progress'], true), 422, 'Only active course enrollments can join a cohort.');
            if ($lockedCohort->county_id !== null) {
                abort_unless($lockedEnrollment->county_id === $lockedCohort->county_id, 422, 'The learner enrollment is outside the cohort county.');
            }
            if (! $actor->programmeRole()->hasNationalScope()) {
                $hasCountyAccess = $lockedCohort->county_id !== null
                    && ($actor->county_id === $lockedCohort->county_id || $actor->assignedCounties()->whereKey($lockedCohort->county_id)->exists());
                abort_unless($hasCountyAccess && $lockedEnrollment->county_id === $lockedCohort->county_id, 403);
            }
            abort_if($lockedCohort->memberships()->count() >= $lockedCohort->capacity, 409, 'The cohort has reached its approved capacity.');
            if ($lockedCohort->memberships()->where('learning_enrollment_id', $lockedEnrollment->id)->withTrashed()->exists()) {
                throw ValidationException::withMessages(['learning_enrollment_id' => 'This enrollment already has a retained cohort membership record.']);
            }

            $membership = $lockedCohort->memberships()->create(['learning_enrollment_id' => $lockedEnrollment->id, 'added_by' => $actor->id, 'joined_at' => now()]);
            $this->auditLogger->record($actor, $membership, 'learning.cohort.member-added', "{$lockedEnrollment->user->name} added to learning cohort {$lockedCohort->code}.", $lockedCohort->county_id, ['learning_enrollment_id' => $lockedEnrollment->id]);

            return $membership;
        });
    }
}
