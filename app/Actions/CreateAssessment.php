<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentScorecardVersion;
use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAssessment
{
    public function __construct(
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, string $countyId, string $assessmentCycleId): Assessment
    {
        abort_unless($actor->can(ProgrammePermission::ManageAssessmentConfiguration->value), 403);
        $county = County::query()->findOrFail($countyId);
        abort_unless($actor->canAccessCounty($county), 403);

        return DB::transaction(function () use ($actor, $county, $assessmentCycleId): Assessment {
            $cycle = AssessmentCycle::query()->lockForUpdate()->findOrFail($assessmentCycleId);
            abort_unless(in_array($cycle->status, ['planned', 'open'], true), 409, __('assessment-record.errors.cycle_not_open'));
            abort_unless($cycle->assessment_scorecard_version_id !== null, 409, __('assessment-record.errors.cycle_scorecard_required'));

            $scorecardVersion = AssessmentScorecardVersion::query()->findOrFail($cycle->assessment_scorecard_version_id);
            abort_unless(in_array($scorecardVersion->status, ['published', 'retired'], true), 409, __('assessment-record.errors.scorecard_not_released'));
            abort_unless(is_string($scorecardVersion->checksum) && mb_strlen($scorecardVersion->checksum) === 64, 409, __('assessment-record.errors.scorecard_checksum'));

            if (Assessment::query()->withTrashed()->where('county_id', $county->id)->where('assessment_cycle_id', $cycle->id)->exists()) {
                throw ValidationException::withMessages([
                    'county_id' => __('assessment-record.errors.duplicate_county_cycle'),
                ]);
            }

            $referenceDataRelease = $this->referenceDataReleaseResolver->forAssessment($county->id, now());
            $assessment = Assessment::create([
                'county_id' => $county->id,
                'assessment_cycle_id' => $cycle->id,
                'assessment_scorecard_version_id' => $scorecardVersion->id,
                'reference_data_release_id' => $referenceDataRelease->id,
                'created_by' => $actor->id,
                'cycle' => $cycle->code,
                'status' => AssessmentStatus::Draft,
            ]);

            $this->auditLogger->record($actor, $assessment, 'assessment.created', __('assessment-record.audit.assessment_created', ['cycle' => $cycle->code, 'county' => $county->name]), $county->id, [
                'assessment_cycle_id' => $cycle->id,
                'assessment_scorecard_version_id' => $scorecardVersion->id,
                'assessment_scorecard_checksum' => $scorecardVersion->checksum,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $assessment->load(['county', 'assessmentCycle', 'scorecardVersion', 'referenceDataRelease', 'creator']);
        }, attempts: 3);
    }
}
