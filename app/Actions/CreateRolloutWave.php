<?php

namespace App\Actions;

use App\Models\County;
use App\Models\RolloutWave;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateRolloutWave
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): RolloutWave
    {
        return DB::transaction(function () use ($actor, $attributes): RolloutWave {
            $countyIds = is_array($attributes['county_ids']) ? array_values(array_filter($attributes['county_ids'], 'is_string')) : [];
            $counties = County::query()->whereKey($countyIds)->get();
            abort_unless($counties->count() === count($countyIds), 422, 'Every rollout county must exist.');
            abort_if($counties->contains(fn (County $county): bool => ! $actor->canAccessCounty($county)), 403, 'A rollout county is outside your authorized scope.');
            $referenceDataRelease = $this->referenceDataReleaseResolver->forRolloutWave($countyIds, now());
            unset($attributes['county_ids']);
            $wave = RolloutWave::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'created_by' => $actor->id, 'status' => 'planning']);
            $countyAssignments = [];
            foreach ($countyIds as $countyId) {
                $countyAssignments[$countyId] = ['readiness_status' => 'planned'];
            }
            $wave->counties()->sync($countyAssignments);
            $this->auditLogger->record($actor, $wave, 'change-readiness.wave.created', "Rollout wave {$wave->code} created with planned capacity, not completion evidence.", metadata: ['county_count' => count($countyIds), 'planned_participants' => $wave->planned_participants, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $wave;
        });
    }
}
