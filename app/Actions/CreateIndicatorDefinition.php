<?php

namespace App\Actions;

use App\Models\IndicatorDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateIndicatorDefinition
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IndicatorDefinition
    {
        return DB::transaction(function () use ($actor, $attributes): IndicatorDefinition {
            $sectorId = is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null;
            $programmeId = is_string($attributes['programme_id'] ?? null) ? $attributes['programme_id'] : null;
            $release = $this->referenceDataReleaseResolver->forIndicatorDefinition($sectorId, $programmeId, now());
            $indicator = IndicatorDefinition::create([...$attributes, 'reference_data_release_id' => $release->id, 'created_by' => $actor->id]);
            $this->auditLogger->record($actor, $indicator, 'indicator.definition.created', "Indicator {$indicator->code} draft created.", metadata: ['reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $indicator;
        });
    }
}
