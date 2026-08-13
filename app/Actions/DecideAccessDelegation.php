<?php

namespace App\Actions;

use App\Models\AccessDelegation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DelegatedAccessResolver;
use Illuminate\Support\Facades\DB;

class DecideAccessDelegation
{
    public function __construct(private AuditLogger $auditLogger, private DelegatedAccessResolver $delegatedAccess) {}

    /** @param array{decision: string, decision_rationale: string} $attributes */
    public function handle(AccessDelegation $accessDelegation, User $approver, array $attributes): AccessDelegation
    {
        return DB::transaction(function () use ($accessDelegation, $approver, $attributes): AccessDelegation {
            $delegation = AccessDelegation::query()->lockForUpdate()->whereKey($accessDelegation->id)->sole();
            abort_unless($delegation->status === 'pending', 409, __('security.delegation.errors.already_decided'));
            abort_if(in_array($approver->id, [$delegation->requested_by, $delegation->beneficiary_id], true), 403, __('security.delegation.errors.independent_approver'));
            abort_unless($approver->two_factor_confirmed_at !== null || $approver->passkeys()->exists(), 409, __('security.delegation.errors.approver_strong_authentication'));
            abort_if($delegation->scope_type === 'national' && ! $approver->programmeRole()->hasNationalScope(), 403, __('security.delegation.errors.permanent_national_approver'));

            if ($attributes['decision'] === 'reject') {
                $delegation->update(['approved_by' => $approver->id, 'approved_at' => now(), 'decision_rationale' => $attributes['decision_rationale'], 'status' => 'rejected']);
            } else {
                abort_if($delegation->expires_at->isPast(), 409, __('security.delegation.errors.expired_approval'));
                $status = $delegation->starts_at->isFuture() ? 'scheduled' : 'active';
                $checksum = hash('sha256', json_encode(['id' => $delegation->id, 'beneficiary_id' => $delegation->beneficiary_id, 'access_type' => $delegation->access_type, 'scope_type' => $delegation->scope_type, 'permissions' => $delegation->permission_scope, 'counties' => $delegation->county_scope_snapshot, 'starts_at' => $delegation->starts_at->toIso8601String(), 'expires_at' => $delegation->expires_at->toIso8601String(), 'approver_id' => $approver->id, 'rationale' => $attributes['decision_rationale']], JSON_THROW_ON_ERROR));
                $delegation->update(['approved_by' => $approver->id, 'approved_at' => now(), 'activated_at' => $status === 'active' ? now() : null, 'decision_rationale' => $attributes['decision_rationale'], 'approval_checksum' => $checksum, 'status' => $status]);
            }

            $this->delegatedAccess->forget($delegation->beneficiary_id);

            $this->auditLogger->record($approver, $delegation, 'security.delegation.'.$attributes['decision'], __('security.delegation.audit.'.$attributes['decision'], ['reference' => $delegation->reference]), metadata: ['status' => $delegation->status, 'checksum' => $delegation->approval_checksum]);

            return $delegation->refresh();
        });
    }
}
