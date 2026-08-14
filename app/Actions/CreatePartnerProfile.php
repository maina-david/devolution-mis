<?php

namespace App\Actions;

use App\Models\County;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreatePartnerProfile
{
    public function __construct(
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): PartnerProfile
    {
        /** @var list<string> $countyIds */
        $countyIds = array_values(array_unique($attributes['county_ids']));
        /** @var list<string> $sectorIds */
        $sectorIds = array_values(array_unique($attributes['sector_ids']));
        /** @var list<string> $userIds */
        $userIds = array_values(array_unique($attributes['user_ids'] ?? []));
        $counties = County::query()->whereKey($countyIds)->get();

        if ($counties->count() !== count($countyIds) || $counties->contains(fn (County $county): bool => ! $actor->canAccessCounty($county))) {
            abort(403, __('partner-coordination.lifecycle.errors.counties_outside_scope'));
        }

        return DB::transaction(function () use ($actor, $attributes, $countyIds, $sectorIds, $userIds): PartnerProfile {
            $referenceDataRelease = $this->referenceDataReleaseResolver->forPartnerProfile(
                $attributes['organization_id'],
                $countyIds,
                $sectorIds,
                now(),
            );
            $partner = PartnerProfile::query()->create([
                ...Arr::except($attributes, ['county_ids', 'sector_ids', 'user_ids']),
                'created_by' => $actor->id,
                'status' => 'active',
                'reference_data_release_id' => $referenceDataRelease->id,
            ]);
            $partner->counties()->sync($countyIds);
            $partner->sectors()->sync($sectorIds);
            $partner->users()->syncWithPivotValues($userIds, ['relationship_role' => 'authorized_representative']);
            $this->auditLogger->record($actor, $partner, 'partner.profile.created', __('partner-coordination.lifecycle.audit.profile_created', ['name' => $partner->organization()->value('name')]), metadata: [
                'county_ids' => $countyIds,
                'sector_ids' => $sectorIds,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $partner->refresh();
        });
    }
}
