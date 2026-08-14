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
            abort_unless($course->status === 'published', 409, __('learning.cohort.errors.published_course_required'));
            abort_unless($instructor->can(ProgrammePermission::ManageLearning->value), 422, __('learning.cohort.errors.instructor_authority_required'));

            $county = is_string($attributes['county_id'] ?? null) ? County::query()->findOrFail($attributes['county_id']) : null;
            if (! $actor->programmeRole()->hasNationalScope()) {
                abort_unless($county && $actor->canAccessCounty($county), 403);
            }
            if ($course->county_id !== null && $course->county_id !== $county?->id) {
                throw ValidationException::withMessages(['county_id' => __('learning.cohort.errors.course_county_mismatch')]);
            }
            if ($county && ! $instructor->programmeRole()->hasNationalScope()) {
                abort_unless($instructor->canAccessCounty($county), 422, __('learning.cohort.errors.instructor_county_scope'));
            }

            $cohort = LearningCohort::query()->create([...$attributes, 'created_by' => $actor->id, 'status' => 'draft']);
            $this->auditLogger->record($actor, $cohort, 'learning.cohort.created', __('learning.cohort.audit.created', ['cohort' => $cohort->code, 'course' => $course->code]), $cohort->county_id, ['capacity' => $cohort->capacity, 'instructor_id' => $cohort->instructor_id]);

            return $cohort;
        });
    }
}
