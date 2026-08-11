<?php

namespace App\Actions;

use App\Models\IntegrationContract;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PublishIntegrationContract
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IntegrationContract $contract, User $actor, array $attributes): IntegrationContract
    {
        return DB::transaction(function () use ($contract, $actor, $attributes): IntegrationContract {
            $contract = IntegrationContract::query()->with('system')->lockForUpdate()->findOrFail($contract->id);
            abort_unless($contract->status === 'review', 409, 'Only contracts in review may be published.');
            if ($contract->submitted_by === $actor->id) {
                throw new AuthorizationException('Separation of duties prevents the submitter from publishing the interface contract.');
            }
            if ($contract->system->environment === 'production' && (blank($attributes['source_owner_approval_reference'] ?? null) || blank($attributes['data_sharing_agreement_reference'] ?? null))) {
                abort(409, 'Production contracts require source-owner approval and data-sharing agreement references.');
            }
            $contract->update(['approved_by' => $actor->id, 'status' => 'published', 'source_owner_approval_reference' => $attributes['source_owner_approval_reference'] ?? null, 'data_sharing_agreement_reference' => $attributes['data_sharing_agreement_reference'] ?? null, 'effective_from' => $attributes['effective_from'] ?? now(), 'effective_to' => $attributes['effective_to'] ?? null, 'published_at' => now()]);
            $this->auditLogger->record($actor, $contract, 'integration.contract.published', "Interface contract {$contract->name} v{$contract->version} published.", null, Arr::only($attributes, ['source_owner_approval_reference', 'data_sharing_agreement_reference']));

            return $contract->refresh();
        });
    }
}
