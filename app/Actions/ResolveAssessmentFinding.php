<?php

namespace App\Actions;

use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class ResolveAssessmentFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentFinding $finding, User $actor, string $resolution): AssessmentFinding
    {
        if ($finding->county_response === null) {
            throw ValidationException::withMessages(['resolution' => 'A county response is required before resolution.']);
        }
        $finding->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
        $this->auditLogger->record($actor, $finding, 'assessment.finding_resolved', "Finding {$finding->code} resolved.", $finding->assessment->county_id);

        return $finding;
    }
}
