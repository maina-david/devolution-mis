<?php

namespace App\Actions;

use App\Models\IntegrationSystem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ActivateIntegrationSystem
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IntegrationSystem $system, User $actor, array $attributes): IntegrationSystem
    {
        return DB::transaction(function () use ($system, $actor, $attributes): IntegrationSystem {
            $system = IntegrationSystem::query()->lockForUpdate()->findOrFail($system->id);
            if ($system->registered_by === $actor->id) {
                throw new AuthorizationException('Separation of duties prevents the registrar from activating a production integration.');
            }
            abort_unless($system->environment === 'production' && $system->transport === 'https_json' && filled($system->base_url) && filled($system->credential_reference), 409, 'Only a fully configured production HTTPS integration may be activated.');
            abort_unless($system->contracts()->where('status', 'published')->exists(), 409, 'At least one independently published contract is required before activation.');
            $system->update([...$attributes, 'status' => 'active', 'health_status' => 'unknown']);
            $this->auditLogger->record($actor, $system, 'integration.system.activated', "Production integration {$system->code} activated against recorded source-owner approval.", null, ['production_approval_reference' => $attributes['production_approval_reference']]);

            return $system->refresh();
        });
    }
}
