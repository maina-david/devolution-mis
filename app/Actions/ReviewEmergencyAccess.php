<?php

namespace App\Actions;

use App\Models\AccessDelegation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewEmergencyAccess
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{post_use_outcome: string, post_use_findings: string} $attributes */
    public function handle(AccessDelegation $accessDelegation, User $reviewer, array $attributes): AccessDelegation
    {
        return DB::transaction(function () use ($accessDelegation, $reviewer, $attributes): AccessDelegation {
            $delegation = AccessDelegation::query()->lockForUpdate()->whereKey($accessDelegation->id)->sole();
            abort_unless($delegation->access_type === 'emergency' && $delegation->status === 'review_pending', 409, 'This emergency grant is not awaiting post-use review.');
            abort_if(in_array($reviewer->id, [$delegation->requested_by, $delegation->beneficiary_id, $delegation->approved_by], true), 403, 'Post-use review requires a fourth independent actor.');
            $delegation->update(['reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'post_use_outcome' => $attributes['post_use_outcome'], 'post_use_findings' => $attributes['post_use_findings'], 'status' => 'reviewed']);
            $this->auditLogger->record($reviewer, $delegation, 'security.delegation.post-use-reviewed', "Emergency access {$delegation->reference} received independent post-use review.", metadata: ['outcome' => $attributes['post_use_outcome']]);

            return $delegation->refresh();
        });
    }
}
