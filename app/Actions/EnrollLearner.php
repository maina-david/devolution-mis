<?php

namespace App\Actions;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\AuditLogger;

class EnrollLearner
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(LearningCourse $course, User $learner): LearningEnrollment
    {
        abort_unless($course->status === 'published', 409, __('learning.enrollment.errors.published_course_required'));
        if ($course->county_id) {
            abort_unless($learner->county_id === $course->county_id || $learner->assignedCounties()->whereKey($course->county_id)->exists() || $learner->programmeRole()->hasNationalScope(), 403);
        } $enrollment = LearningEnrollment::query()->firstOrCreate(['learning_course_id' => $course->id, 'user_id' => $learner->id], ['county_id' => $learner->county_id, 'status' => 'enrolled', 'progress_percentage' => 0, 'enrolled_at' => now(), 'enrolled_by' => $learner->id]);
        $this->auditLogger->record($learner, $enrollment, 'learning.enrollment.created', __('learning.enrollment.audit.created', ['course' => $course->code]), $learner->county_id);

        return $enrollment;
    }
}
