<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TransitionAssessment
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, AssessmentStatus $status, User $actor, ?float $score = null): Assessment
    {
        $assessment = DB::transaction(function () use ($assessment, $status, $actor, $score): Assessment {
            $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $locked->update([
                'status' => $status,
                'score' => $score ?? $locked->score,
                'assessor_id' => $status === AssessmentStatus::UnderAssessment ? $actor->id : $locked->assessor_id,
                'assessed_at' => $status === AssessmentStatus::Assessed ? now() : $locked->assessed_at,
            ]);

            return $locked;
        });

        $this->notifyNextActors($assessment, $status);
        $this->auditLogger->record($actor, $assessment, "assessment.{$status->value}", __('assessment-record.audit.assessment_transitioned', [
            'status' => __('assessment-record.statuses.'.$status->value),
        ]), $assessment->county_id, ['score' => $score]);

        return $assessment;
    }

    private function notifyNextActors(Assessment $assessment, AssessmentStatus $status): void
    {
        $recipients = match ($status) {
            AssessmentStatus::Submitted => User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::Assessor->value))->whereHas('assignedCounties', fn ($query) => $query->whereKey($assessment->county_id))->get(),
            AssessmentStatus::Assessed => User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', [UserRole::TopManagement->value, UserRole::DevolutionAdmin->value]))->where(fn ($query) => $query->whereHas('assignedCounties', fn ($assigned) => $assigned->whereKey($assessment->county_id))->orWhereHas('roles', fn ($roles) => $roles->where('name', UserRole::DevolutionAdmin->value)))->get(),
            AssessmentStatus::Approved => User::query()->where('county_id', $assessment->county_id)->get(),
            default => collect(),
        };

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, ProgrammeAlert::translated(
            titleKey: 'assessment-record.notifications.workflow_updated_title',
            messageKey: 'assessment-record.notifications.workflow_updated_message',
            category: 'assessment',
            messageParameters: [
                'cycle' => $assessment->cycle,
                'county' => $assessment->county->name,
            ],
        ));
    }
}
