<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class RespondToAssessmentFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentFinding $finding, User $actor, string $response): AssessmentFinding
    {
        abort_unless($actor->can(ProgrammePermission::SubmitAssessment->value) && $actor->canAccessCounty($finding->assessment->county), 403, __('assessment-record.errors.finding_response_unauthorized'));

        if (! in_array($finding->status, ['open', 'clarification_requested', 'responded'], true)) {
            throw ValidationException::withMessages(['response' => __('assessment-record.errors.finding_not_active')]);
        }
        $finding->update(['county_response' => $response, 'status' => 'responded', 'responded_at' => now()]);
        $this->auditLogger->record($actor, $finding, 'assessment.finding_responded', __('assessment-record.audit.finding_responded', ['code' => $finding->code]), $finding->assessment->county_id);

        return $finding;
    }
}
