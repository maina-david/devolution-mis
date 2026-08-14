<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\AccessReviewItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProgrammeAuthorization;
use Illuminate\Support\Facades\DB;

class ReinstateUserAccess
{
    public function __construct(private ProgrammeAuthorization $authorization, private AuditLogger $auditLogger) {}

    /** @param array{rationale: string, approval_reference: string} $attributes */
    public function handle(AccessReviewItem $item, User $actor, array $attributes): AccessReviewItem
    {
        return DB::transaction(function () use ($item, $actor, $attributes): AccessReviewItem {
            $item = AccessReviewItem::query()->with('user')->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->decision === 'revoke' && $item->revoked_at !== null, 409, __('security.access_review.reinstatement.errors.only_revoked'));
            abort_if($item->reviewed_by === $actor->id, 403, __('security.access_review.reinstatement.errors.independent_reinstater'));
            abort_if($item->reinstated_at !== null, 409, __('security.access_review.reinstatement.errors.already_reinstated'));
            $user = $item->user;
            abort_unless($user instanceof User && $user->access_revoked_at !== null, 409, __('security.access_review.reinstatement.errors.identity_not_suspended'));
            abort_if(in_array($item->role_name, config('security-governance.privileged_roles'), true) && $user->two_factor_confirmed_at === null && ! $user->passkeys()->exists(), 409, __('security.access_review.reinstatement.errors.strong_authentication_required'));

            $this->authorization->assignRole($user, UserRole::from($item->role_name));
            $user->assignedCounties()->sync(collect($item->assigned_county_snapshot)->pluck('id'));
            $user->forceFill(['access_revoked_at' => null, 'access_revoked_by' => null, 'access_revocation_reason' => null])->save();
            $item->update(['reinstated_by' => $actor->id, 'reinstated_at' => now(), 'reinstatement_rationale' => __('security.access_review.reinstatement.rationale_with_approval', ['rationale' => $attributes['rationale'], 'approval_reference' => $attributes['approval_reference']])]);
            $this->auditLogger->record($actor, $item, 'security.access-review.reinstated', __('security.access_review.reinstatement.audit', ['role' => $item->role_name]), metadata: ['approval_reference' => $attributes['approval_reference']]);

            return $item->refresh();
        });
    }
}
