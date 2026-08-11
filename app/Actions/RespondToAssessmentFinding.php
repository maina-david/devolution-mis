<?php

namespace App\Actions;

use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class RespondToAssessmentFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentFinding $finding, User $actor, string $response): AssessmentFinding
    {
        if (! in_array($finding->status, ['open', 'clarification_requested', 'responded'], true)) {
            throw ValidationException::withMessages(['response' => 'Only an active finding may receive a response.']);
        }
        $finding->update(['county_response' => $response, 'status' => 'responded', 'responded_at' => now()]);
        $this->auditLogger->record($actor, $finding, 'assessment.finding_responded', "Finding {$finding->code} response submitted.", $finding->assessment->county_id);

        return $finding;
    }
}
