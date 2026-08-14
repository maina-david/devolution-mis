<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class ResolveAssessmentFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentFinding $finding, User $actor, string $resolution): AssessmentFinding
    {
        abort_unless($actor->can(ProgrammePermission::ReviewAssessment->value) && $actor->canAccessCounty($finding->assessment->county), 403, __('assessment-record.errors.finding_resolution_unauthorized'));

        if ($finding->county_response === null) {
            throw ValidationException::withMessages(['resolution' => __('assessment-record.errors.finding_response_required')]);
        }
        $finding->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
        $this->auditLogger->record($actor, $finding, 'assessment.finding_resolved', __('assessment-record.audit.finding_resolved', ['code' => $finding->code]), $finding->assessment->county_id);

        return $finding;
    }
}
