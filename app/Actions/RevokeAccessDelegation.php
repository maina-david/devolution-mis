<?php

namespace App\Actions;

use App\Models\AccessDelegation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DelegatedAccessResolver;
use Illuminate\Support\Facades\DB;

class RevokeAccessDelegation
{
    public function __construct(private AuditLogger $auditLogger, private DelegatedAccessResolver $delegatedAccess) {}

    public function handle(AccessDelegation $accessDelegation, User $actor, string $reason): AccessDelegation
    {
        return DB::transaction(function () use ($accessDelegation, $actor, $reason): AccessDelegation {
            $delegation = AccessDelegation::query()->lockForUpdate()->whereKey($accessDelegation->id)->sole();
            abort_unless(in_array($delegation->status, ['scheduled', 'active'], true), 409, __('security.delegation.errors.revocable_status'));
            $delegation->update(['revoked_by' => $actor->id, 'revoked_at' => now(), 'revocation_reason' => $reason, 'status' => 'revoked']);
            $this->delegatedAccess->forget($delegation->beneficiary_id);
            $this->auditLogger->record($actor, $delegation, 'security.delegation.revoked', __('security.delegation.audit.revoked', ['reference' => $delegation->reference]), metadata: ['reason' => $reason]);

            return $delegation->refresh();
        });
    }
}
