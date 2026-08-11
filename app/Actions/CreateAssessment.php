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
            abort_unless(in_array($cycle->status, ['planned', 'open'], true), 409, 'Assessments can only be initiated for planned or open cycles.');
            abort_unless($cycle->assessment_scorecard_version_id !== null, 409, 'The assessment cycle must pin a released scorecard version.');

            $scorecardVersion = AssessmentScorecardVersion::query()->findOrFail($cycle->assessment_scorecard_version_id);
            abort_unless(in_array($scorecardVersion->status, ['published', 'retired'], true), 409, 'The assessment cycle scorecard has not been released.');
            abort_unless(is_string($scorecardVersion->checksum) && mb_strlen($scorecardVersion->checksum) === 64, 409, 'The assessment cycle scorecard has no valid integrity checksum.');

            if (Assessment::query()->withTrashed()->where('county_id', $county->id)->where('assessment_cycle_id', $cycle->id)->exists()) {
                throw ValidationException::withMessages([
                    'county_id' => 'An assessment already exists for this county and cycle.',
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

            $this->auditLogger->record($actor, $assessment, 'assessment.created', "Assessment {$cycle->code} initiated for {$county->name} County.", $county->id, [
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
