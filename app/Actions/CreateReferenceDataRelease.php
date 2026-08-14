<?php

namespace App\Actions;

use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateReferenceDataRelease
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array<string, mixed> $auditMetadata */
    public function handle(User $actor, string $changeSummary, array $auditMetadata = []): ReferenceDataRelease
    {
        return Cache::lock('reference-data-release-version', 10)->block(5, function () use ($actor, $auditMetadata, $changeSummary): ReferenceDataRelease {
            return DB::transaction(function () use ($actor, $auditMetadata, $changeSummary): ReferenceDataRelease {
                $snapshot = $this->snapshot();
                $release = ReferenceDataRelease::create([
                    'version' => ((int) ReferenceDataRelease::query()->withTrashed()->max('version')) + 1,
                    'submitted_by' => $actor->id,
                    'status' => 'submitted',
                    'change_summary' => $changeSummary,
                    'snapshot' => $snapshot,
                    'checksum' => $this->canonicalJson->checksum($snapshot),
                    'submitted_at' => now(),
                ]);
                $this->auditLogger->record($actor, $release, 'reference.release.submitted', __('reference-data.governance.audit.release_submitted', ['version' => $release->version]), metadata: [
                    'checksum' => $release->checksum,
                    ...$auditMetadata,
                ]);

                return $release;
            });
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'counties' => County::query()->orderBy('code')->get()->map(fn (County $county): array => [
                'id' => $county->id, 'code' => $county->code, 'name' => $county->name, 'slug' => $county->slug, 'region' => $county->region,
                'official_website_url' => $county->official_website_url, 'logo_checksum_sha256' => $county->logo_checksum_sha256,
            ])->all(),
            'organizations' => Organization::query()->orderBy('code')->get()->map(fn (Organization $organization): array => [
                'id' => $organization->id, 'code' => $organization->code, 'name' => $organization->name, 'type' => $organization->type,
                'county_id' => $organization->county_id, 'status' => $organization->status,
            ])->all(),
            'sectors' => Sector::query()->orderBy('code')->get()->map(fn (Sector $sector): array => [
                'id' => $sector->id, 'parent_sector_id' => $sector->parent_sector_id, 'code' => $sector->code, 'name' => $sector->name, 'description' => $sector->description, 'is_active' => $sector->is_active,
            ])->all(),
            'programmes' => Programme::query()->orderBy('code')->get()->map(fn (Programme $programme): array => [
                'id' => $programme->id, 'code' => $programme->code, 'name' => $programme->name, 'description' => $programme->description,
                'lead_organization_id' => $programme->lead_organization_id, 'sector_id' => $programme->sector_id,
                'starts_on' => $programme->starts_on?->toDateString(), 'ends_on' => $programme->ends_on?->toDateString(),
                'status' => $programme->status, 'budget_amount' => $programme->budget_amount, 'currency' => $programme->currency,
            ])->all(),
            'programme_county_coverages' => ProgrammeCountyCoverage::query()->orderBy('programme_id')->orderBy('county_id')->orderBy('starts_on')->get()->map(fn (ProgrammeCountyCoverage $coverage): array => [
                'id' => $coverage->id, 'programme_id' => $coverage->programme_id, 'county_id' => $coverage->county_id,
                'implementation_lead_id' => $coverage->implementation_lead_id, 'starts_on' => $coverage->starts_on->toDateString(),
                'ends_on' => $coverage->ends_on?->toDateString(), 'status' => $coverage->status,
                'funding_allocation' => $coverage->funding_allocation, 'currency' => $coverage->currency,
                'source_reference' => $coverage->source_reference,
            ])->all(),
            'sub_counties' => SubCounty::query()->orderBy('county_id')->orderBy('code')->get()->map(fn (SubCounty $subCounty): array => [
                'id' => $subCounty->id, 'county_id' => $subCounty->county_id, 'code' => $subCounty->code, 'name' => $subCounty->name,
                'classification' => $subCounty->classification, 'source_authority' => $subCounty->source_authority, 'source_reference' => $subCounty->source_reference,
                'source_checksum_sha256' => $subCounty->source_checksum_sha256, 'boundary_checksum_sha256' => $subCounty->boundary_checksum_sha256,
                'effective_from' => $subCounty->effective_from->toDateString(), 'effective_to' => $subCounty->effective_to?->toDateString(),
            ])->all(),
            'wards' => Ward::query()->orderBy('sub_county_id')->orderBy('code')->get()->map(fn (Ward $ward): array => [
                'id' => $ward->id, 'sub_county_id' => $ward->sub_county_id, 'code' => $ward->code, 'name' => $ward->name,
                'source_authority' => $ward->source_authority, 'source_reference' => $ward->source_reference,
                'source_checksum_sha256' => $ward->source_checksum_sha256, 'boundary_checksum_sha256' => $ward->boundary_checksum_sha256,
                'effective_from' => $ward->effective_from->toDateString(), 'effective_to' => $ward->effective_to?->toDateString(),
            ])->all(),
        ];
    }
}
