<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\LearningCohort;
use App\Models\LearningCourse;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLearningCohort
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): LearningCohort
    {
        return DB::transaction(function () use ($actor, $attributes): LearningCohort {
            $course = LearningCourse::query()->whereKey($attributes['learning_course_id'])->lockForUpdate()->firstOrFail();
            $instructor = User::query()->whereKey($attributes['instructor_id'])->firstOrFail();
            abort_unless($course->status === 'published', 409, 'Only published courses can be assigned to a delivery cohort.');
            abort_unless($instructor->can(ProgrammePermission::ManageLearning->value), 422, 'The selected instructor must hold learning-management authority.');

            $county = is_string($attributes['county_id'] ?? null) ? County::query()->findOrFail($attributes['county_id']) : null;
            if (! $actor->programmeRole()->hasNationalScope()) {
                abort_unless($county && $actor->canAccessCounty($county), 403);
            }
            if ($course->county_id !== null && $course->county_id !== $county?->id) {
                throw ValidationException::withMessages(['county_id' => 'A county-targeted course can only be delivered by a cohort in that county.']);
            }
            if ($county && ! $instructor->programmeRole()->hasNationalScope()) {
                abort_unless($instructor->canAccessCounty($county), 422, 'The selected instructor is not authorized for the cohort county.');
            }

            $cohort = LearningCohort::query()->create([...$attributes, 'created_by' => $actor->id, 'status' => 'draft']);
            $this->auditLogger->record($actor, $cohort, 'learning.cohort.created', "Learning cohort {$cohort->code} created for {$course->code}.", $cohort->county_id, ['capacity' => $cohort->capacity, 'instructor_id' => $cohort->instructor_id]);

            return $cohort;
        });
    }
}
