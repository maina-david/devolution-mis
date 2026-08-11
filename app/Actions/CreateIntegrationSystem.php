<?php

namespace App\Actions;

use App\Models\IntegrationSystem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class CreateIntegrationSystem
{
    public function __construct(private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IntegrationSystem
    {
        return DB::transaction(function () use ($actor, $attributes): IntegrationSystem {
            $organizationId = is_string($attributes['owner_organization_id'] ?? null) ? $attributes['owner_organization_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forIntegrationSystem($organizationId, now());
            $system = IntegrationSystem::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'registered_by' => $actor->id]);
            $this->auditLogger->record($actor, $system, 'integration.system.created', "Integration system {$system->code} registered.", null, ['reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $system;
        });
    }
}
