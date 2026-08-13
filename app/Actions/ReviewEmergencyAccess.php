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
            abort_unless($delegation->access_type === 'emergency' && $delegation->status === 'review_pending', 409, __('security.delegation.errors.review_not_pending'));
            abort_if(in_array($reviewer->id, [$delegation->requested_by, $delegation->beneficiary_id, $delegation->approved_by], true), 403, __('security.delegation.errors.independent_post_use_reviewer'));
            $delegation->update(['reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'post_use_outcome' => $attributes['post_use_outcome'], 'post_use_findings' => $attributes['post_use_findings'], 'status' => 'reviewed']);
            $this->auditLogger->record($reviewer, $delegation, 'security.delegation.post-use-reviewed', __('security.delegation.audit.post_use_reviewed', ['reference' => $delegation->reference]), metadata: ['outcome' => $attributes['post_use_outcome']]);

            return $delegation->refresh();
        });
    }
}
