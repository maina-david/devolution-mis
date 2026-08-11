<?php

namespace App\Actions;

use App\Models\AssessmentAppeal;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class DecideAssessmentAppeal
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentAppeal $appeal, User $actor, string $status, string $decision): AssessmentAppeal
    {
        if (! in_array($appeal->status, ['submitted', 'under_review'], true)) {
            throw ValidationException::withMessages(['decision' => 'This appeal has already been decided.']);
        }
        $appeal->update(['status' => $status, 'decision' => $decision, 'reviewer_id' => $actor->id, 'decided_at' => now()]);
        $this->auditLogger->record($actor, $appeal, 'assessment.appeal_decided', "Assessment appeal {$status}.", $appeal->assessment->county_id, ['decision' => $decision]);

        return $appeal;
    }
}
