<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;

class RecordAssessmentFinding
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{assessment_criterion_id?: string|null, code: string, severity: string, title: string, description: string, assigned_to?: string|null, response_due_at?: mixed} $data */
    public function handle(Assessment $assessment, User $actor, array $data): AssessmentFinding
    {
        abort_unless($actor->can(ProgrammePermission::ReviewAssessment->value) && $actor->canAccessCounty($assessment->county), 403, __('assessment-record.errors.finding_record_unauthorized'));

        $finding = $assessment->findings()->create([...$data, 'raised_by' => $actor->id]);
        $this->auditLogger->record($actor, $finding, 'assessment.finding_raised', __('assessment-record.audit.finding_raised', ['code' => $finding->code]), $assessment->county_id, ['severity' => $finding->severity]);

        return $finding;
    }
}
