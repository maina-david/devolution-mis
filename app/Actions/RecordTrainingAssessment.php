<?php

namespace App\Actions;

use App\Models\TrainingAssessment;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordTrainingAssessment
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(TrainingParticipant $participant, User $actor, array $attributes): TrainingAssessment
    {
        if ($participant->user_id === $actor->id) {
            throw new HttpException(403, 'Participants cannot assess their own competency.');
        }

        return DB::transaction(function () use ($participant, $actor, $attributes): TrainingAssessment {
            $participant = TrainingParticipant::query()->with('cohort')->lockForUpdate()->findOrFail($participant->id);
            if ($participant->assessments()->where('assessment_type', $attributes['assessment_type'])->exists()) {
                throw new ConflictHttpException('This assessment type is already recorded and retained as evidence.');
            }
            $outcome = (float) $attributes['score'] >= (float) $participant->cohort->passing_score ? 'competent' : 'development_required';
            $assessment = TrainingAssessment::create([...$attributes, 'training_participant_id' => $participant->id, 'assessed_by' => $actor->id, 'outcome' => $outcome, 'assessed_at' => now()]);
            $attendanceComplete = (float) $attributes['attended_hours'] >= (float) $participant->cohort->minimum_attendance_hours;
            $participant->update(['attended_hours' => $attributes['attended_hours'], 'attendance_status' => $attendanceComplete ? 'completed' : 'partial', 'competency_status' => $outcome, 'completed_at' => $attendanceComplete && $outcome === 'competent' ? now() : null]);
            $this->auditLogger->record($actor, $assessment, 'change-readiness.competency.recorded', "{$attributes['assessment_type']} competency evidence recorded with {$outcome} outcome.", $participant->county_id, ['score' => $attributes['score'], 'attended_hours' => $attributes['attended_hours']]);

            return $assessment;
        });
    }
}
