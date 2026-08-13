<?php

namespace App\Actions;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DecideAccessReviewItem
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{decision: string, rationale: string, remediation_action?: string|null, remediation_due_at?: string|null} $attributes */
    public function handle(AccessReviewItem $item, User $reviewer, array $attributes): AccessReviewItem
    {
        return DB::transaction(function () use ($item, $reviewer, $attributes): AccessReviewItem {
            $item = AccessReviewItem::query()->with(['campaign', 'user'])->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->campaign->status === 'open', 409, __('security.access_review.errors.campaign_closed'));
            abort_unless($item->campaign->reviewer_id === $reviewer->id, 403, __('security.access_review.errors.assigned_reviewer_required'));
            abort_if($item->user_id === $reviewer->id, 403, __('security.access_review.errors.self_certification'));
            abort_unless($item->decision === 'pending', 409, __('security.access_review.errors.already_decided'));
            $user = $item->user;
            abort_unless($user instanceof User, 409, __('security.access_review.errors.identity_missing'));

            if ($attributes['decision'] === 'retain' && in_array($item->role_name, config('security-governance.privileged_roles'), true)) {
                abort_unless($user->two_factor_confirmed_at !== null || $user->passkeys()->exists(), 409, __('security.access_review.errors.strong_authentication_required'));
            }

            $sessionsRevoked = 0;
            if ($attributes['decision'] === 'revoke') {
                $sessions = DB::table((string) config('session.table'))->where('user_id', $user->id);
                $sessionsRevoked = $sessions->count();
                $sessions->delete();
                $user->syncRoles([]);
                $user->assignedCounties()->detach();
                $user->forceFill(['access_revoked_at' => now(), 'access_revoked_by' => $reviewer->id, 'access_revocation_reason' => $attributes['rationale'], 'remember_token' => Str::random(60)])->save();
            }

            $item->update(['reviewed_by' => $reviewer->id, 'decision' => $attributes['decision'], 'rationale' => $attributes['rationale'], 'remediation_action' => $attributes['remediation_action'] ?? null, 'remediation_due_at' => $attributes['remediation_due_at'] ?? null, 'reviewed_at' => now(), 'revoked_at' => $attributes['decision'] === 'revoke' ? now() : null, 'sessions_revoked' => $sessionsRevoked]);
            $this->refreshCampaign($item->campaign);
            $this->auditLogger->record($reviewer, $item, 'security.access-review.decided', __('security.access_review.audit.decided', ['decision' => $attributes['decision'], 'role' => $item->role_name]), metadata: ['decision' => $attributes['decision'], 'sessions_revoked' => $sessionsRevoked]);

            return $item->refresh();
        });
    }

    private function refreshCampaign(AccessReviewCampaign $campaign): void
    {
        $items = $campaign->items()->orderBy('id')->get(['id', 'user_id', 'role_name', 'decision', 'reviewed_by', 'reviewed_at']);
        $pending = $items->where('decision', 'pending')->count();
        $attributes = ['retained_count' => $items->where('decision', 'retain')->count(), 'revoked_count' => $items->where('decision', 'revoke')->count(), 'remediation_count' => $items->where('decision', 'remediate')->count()];
        if ($pending === 0) {
            $attributes = [...$attributes, 'status' => 'completed', 'completed_at' => now(), 'evidence_checksum' => hash('sha256', json_encode($items->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))];
        }
        $campaign->update($attributes);
    }
}
