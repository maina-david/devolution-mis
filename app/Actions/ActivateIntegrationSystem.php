<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ManageIntegrations->value), 403, __('integrations.exchange.errors.system_activation_unauthorized'));

        return DB::transaction(function () use ($system, $actor, $attributes): IntegrationSystem {
            $system = IntegrationSystem::query()->lockForUpdate()->findOrFail($system->id);
            if ($system->registered_by === $actor->id) {
                throw new AuthorizationException(__('integrations.exchange.errors.system_registrar_separation'));
            }
            abort_unless($system->environment === 'production' && $system->transport === 'https_json' && filled($system->base_url) && filled($system->credential_reference), 409, __('integrations.exchange.errors.production_https_configuration_required'));
            abort_unless($system->contracts()->where('status', 'published')->exists(), 409, __('integrations.exchange.errors.published_contract_required'));
            $system->update([...$attributes, 'status' => 'active', 'health_status' => 'unknown']);
            $this->auditLogger->record($actor, $system, 'integration.system.activated', __('integrations.exchange.audit.system_activated', ['code' => $system->code]), null, ['production_approval_reference' => $attributes['production_approval_reference']]);

            return $system->refresh();
        });
    }
}
