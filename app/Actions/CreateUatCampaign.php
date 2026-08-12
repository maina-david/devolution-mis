<?php

namespace App\Actions;

use App\Models\County;
use App\Models\UatCampaign;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateUatCampaign
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $releaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): UatCampaign
    {
        return DB::transaction(function () use ($actor, $attributes): UatCampaign {
            $countyIds = array_values(array_unique(array_filter($attributes['county_ids'], 'is_string')));
            $counties = County::query()->whereKey($countyIds)->get();
            abort_unless($counties->count() === count($countyIds), 422, __('change-readiness.uat_errors.county_missing'));
            abort_if($counties->contains(fn (County $county): bool => ! $actor->canAccessCounty($county)), 403, __('change-readiness.uat_errors.county_scope'));
            $release = $this->releaseResolver->forUatCampaign($countyIds, now());
            unset($attributes['county_ids']);
            $campaign = UatCampaign::create([...$attributes, 'created_by' => $actor->id, 'reference_data_release_id' => $release->id, 'status' => 'planning']);
            $campaign->counties()->sync(collect($countyIds)->mapWithKeys(fn (string $countyId): array => [$countyId => ['participation_status' => 'planned']])->all());
            $this->auditLogger->record($actor, $campaign, 'change-readiness.uat.campaign.created', "UAT campaign {$campaign->code} planned without recording acceptance.", metadata: ['county_count' => count($countyIds), 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $campaign;
        });
    }
}
