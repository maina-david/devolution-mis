<?php

namespace App\Services;

use App\Models\ReferenceDataRelease;
use App\Support\CanonicalJson;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EffectiveReferenceDataReleaseResolver
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $countyIds
     */
    public function forProject(array $attributes, array $countyIds, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'project');

        $sectorId = $attributes['sector_id'] ?? null;
        abort_unless(is_string($sectorId), 422, __('reference-data.resolver.errors.governed_sector_required'));

        $this->assertContains($release, 'counties', $countyIds, 'county_ids');
        $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');

        if (is_string($attributes['programme_id'] ?? null)) {
            $this->assertContains($release, 'programmes', [$attributes['programme_id']], 'programme_id');
        }

        if (is_string($attributes['funding_organization_id'] ?? null)) {
            $this->assertContains($release, 'organizations', [$attributes['funding_organization_id']], 'funding_organization_id');
        }

        return $release;
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<string>  $sectorIds
     */
    public function forPartnerProfile(string $organizationId, array $countyIds, array $sectorIds, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'partner_profile');

        $this->assertContains($release, 'organizations', [$organizationId], 'organization_id');
        $this->assertContains($release, 'counties', $countyIds, 'county_ids');
        $this->assertContains($release, 'sectors', $sectorIds, 'sector_ids');

        return $release;
    }

    public function forPartnerCollaborationAction(string $countyId, ?string $organizationId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'partner_collaboration_action');

        $this->assertContains($release, 'counties', [$countyId], 'county_id');
        if ($organizationId !== null) {
            $this->assertContains($release, 'organizations', [$organizationId], 'accountable_organization_id');
        }

        return $release;
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<string>  $sectorIds
     */
    public function forDswgWorkingGroup(?string $leadOrganizationId, array $countyIds, array $sectorIds, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'sector_working_group');

        if ($leadOrganizationId !== null) {
            $this->assertContains($release, 'organizations', [$leadOrganizationId], 'lead_organization_id');
        }
        $this->assertContains($release, 'counties', $countyIds, 'county_ids');
        $this->assertContains($release, 'sectors', $sectorIds, 'sector_ids');

        return $release;
    }

    public function forDswgAction(?string $countyId, ?string $organizationId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'dswg_action');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }
        if ($organizationId !== null) {
            $this->assertContains($release, 'organizations', [$organizationId], 'accountable_organization_id');
        }

        return $release;
    }

    public function forAnalyticsDashboard(?string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'analytics_dashboard');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }

        return $release;
    }

    public function forReportSchedule(?string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'scheduled_report');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }

        return $release;
    }

    public function forSupportTicket(?string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'service_desk_ticket');
        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }

        return $release;
    }

    public function forIndicatorDefinition(?string $sectorId, ?string $programmeId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'indicator_definition');
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }
        if ($programmeId !== null) {
            $this->assertContains($release, 'programmes', [$programmeId], 'programme_id');
        }

        return $release;
    }

    public function forInnovationReplication(string $sourceCountyId, string $targetCountyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'innovation_replication');
        $this->assertContains($release, 'counties', [$sourceCountyId, $targetCountyId], 'county_ids');

        return $release;
    }

    public function forExchequerRequest(string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'exchequer_request');
        $this->assertContains($release, 'counties', [$countyId], 'county_id');

        return $release;
    }

    /** @param list<string> $countyIds */
    public function forAccessDelegation(array $countyIds, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'temporary_access');
        $this->assertContains($release, 'counties', $countyIds, 'county_ids');

        return $release;
    }

    public function forTravelRequest(?string $organizationId, ?string $countyId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'travel_clearance');

        if ($organizationId !== null) {
            $this->assertContains($release, 'organizations', [$organizationId], 'organization_id');
        }
        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forProgrammeEvaluation(?string $programmeId, ?string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'programme_evaluation');

        if ($programmeId !== null) {
            $this->assertContains($release, 'programmes', [$programmeId], 'programme_id');
        }
        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }

        return $release;
    }

    /**
     * @param  list<string>  $countyIds
     * @param  list<string>  $organizationIds
     */
    public function forIgrResolution(array $countyIds, array $organizationIds, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'intergovernmental_resolution');

        $this->assertContains($release, 'counties', $countyIds, 'assignments');
        $this->assertContains($release, 'organizations', $organizationIds, 'assignments');

        return $release;
    }

    public function forAssessment(string $countyId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'county_performance_assessment');

        $this->assertContains($release, 'counties', [$countyId], 'county_id');

        return $release;
    }

    public function forCitizenCase(string $countyId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'citizen_case');

        $this->assertContains($release, 'counties', [$countyId], 'county_id');
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forCitizenCaseTriage(?string $organizationId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'citizen_case_triage');

        if ($organizationId !== null) {
            $this->assertContains($release, 'organizations', [$organizationId], 'assigned_organization_id');
        }
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forLearningCourse(?string $countyId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'learning_course');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forKnowledgeItem(?string $countyId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'knowledge_resource');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forDevolutionInnovation(?string $countyId, ?string $sectorId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'devolution_innovation');

        if ($countyId !== null) {
            $this->assertContains($release, 'counties', [$countyId], 'county_id');
        }
        if ($sectorId !== null) {
            $this->assertContains($release, 'sectors', [$sectorId], 'sector_id');
        }

        return $release;
    }

    public function forPerformancePlan(?string $organizationId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'performance_plan');

        if ($organizationId !== null) {
            $this->assertContains($release, 'organizations', [$organizationId], 'organization_id');
        }

        return $release;
    }

    public function forIntegrationSystem(?string $ownerOrganizationId, CarbonInterface $effectiveAt): ReferenceDataRelease
    {
        $release = $this->effectiveRelease($effectiveAt, 'integration_system');

        if ($ownerOrganizationId !== null) {
            $this->assertContains($release, 'organizations', [$ownerOrganizationId], 'owner_organization_id');
        }

        return $release;
    }

    public function availableForCitizenIntake(CarbonInterface $effectiveAt): ?ReferenceDataRelease
    {
        return $this->availableForSelection($effectiveAt);
    }

    public function availableForSelection(CarbonInterface $effectiveAt): ?ReferenceDataRelease
    {
        $release = $this->findEffectiveRelease($effectiveAt);

        if ($release === null || ! hash_equals($release->checksum, $this->canonicalJson->checksum($release->snapshot))) {
            return null;
        }

        return $release;
    }

    private function effectiveRelease(CarbonInterface $effectiveAt, string $operation): ReferenceDataRelease
    {
        $release = $this->findEffectiveRelease($effectiveAt);

        if ($release === null) {
            abort(409, __('reference-data.resolver.errors.no_effective_release', [
                'operation' => __("reference-data.resolver.operations.{$operation}"),
            ]));
        }
        abort_unless(
            hash_equals($release->checksum, $this->canonicalJson->checksum($release->snapshot)),
            409,
            __('reference-data.resolver.errors.checksum_failed'),
        );

        return $release;
    }

    private function findEffectiveRelease(CarbonInterface $effectiveAt): ?ReferenceDataRelease
    {
        return ReferenceDataRelease::query()
            ->where('status', 'published')
            ->whereNotNull('effective_from')
            ->where('effective_from', '<=', $effectiveAt)
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }

    /** @param list<string> $requiredIds */
    private function assertContains(ReferenceDataRelease $release, string $catalogue, array $requiredIds, string $attribute): void
    {
        $records = $release->snapshot[$catalogue] ?? [];
        $availableIds = collect($records)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id))
            ->all();
        $missingIds = array_values(array_diff($requiredIds, $availableIds));

        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                $attribute => __('reference-data.resolver.errors.records_missing', ['version' => $release->version]),
            ]);
        }
    }
}
