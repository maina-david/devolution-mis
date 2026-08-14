<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateDswgWorkingGroup
{
    public function __construct(
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): DswgWorkingGroup
    {
        abort_unless($actor->can(ProgrammePermission::ManageDswg->value), 403, __('dswg.group_create_unauthorized'));

        /** @var list<string> $countyIds */
        $countyIds = array_values(array_unique($attributes['county_ids']));
        /** @var list<string> $sectorIds */
        $sectorIds = array_values(array_unique($attributes['sector_ids']));
        /** @var list<string> $memberIds */
        $memberIds = array_values(array_unique([...$attributes['member_ids'], $attributes['secretariat_user_id']]));
        $counties = County::query()->whereKey($countyIds)->get();

        if ($counties->count() !== count($countyIds) || $counties->contains(fn (County $county): bool => ! $actor->canAccessCounty($county))) {
            abort(403, __('dswg.group_county_outside_scope'));
        }

        return DB::transaction(function () use ($actor, $attributes, $countyIds, $sectorIds, $memberIds): DswgWorkingGroup {
            $leadOrganizationId = is_string($attributes['lead_organization_id'] ?? null) ? $attributes['lead_organization_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forDswgWorkingGroup(
                $leadOrganizationId,
                $countyIds,
                $sectorIds,
                now(),
            );
            $group = DswgWorkingGroup::query()->create([
                ...Arr::except($attributes, ['county_ids', 'sector_ids', 'member_ids']),
                'created_by' => $actor->id,
                'status' => 'active',
                'reference_data_release_id' => $referenceDataRelease->id,
            ]);
            $group->counties()->sync($countyIds);
            $group->sectors()->sync($sectorIds);
            $group->members()->syncWithPivotValues($memberIds, ['membership_role' => 'member', 'status' => 'active']);
            $group->members()->updateExistingPivot($attributes['secretariat_user_id'], ['membership_role' => 'secretariat']);
            $this->auditLogger->record($actor, $group, 'dswg.group.created', __('dswg.audit_group_created', ['code' => $group->code]), metadata: [
                'county_ids' => $countyIds,
                'sector_ids' => $sectorIds,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $group->refresh();
        });
    }
}
