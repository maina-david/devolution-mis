<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\ServiceDeskPolicy;
use App\Models\ServiceDeskRosterMember;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class PublishServiceDeskPolicy
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array{authority_status: string, approval_reference?: string|null} $decision */
    public function handle(ServiceDeskPolicy $serviceDeskPolicy, User $publisher, array $decision): ServiceDeskPolicy
    {
        return DB::transaction(function () use ($serviceDeskPolicy, $publisher, $decision): ServiceDeskPolicy {
            $policy = ServiceDeskPolicy::query()->with(['businessCalendar', 'rosterMembers.user', 'rosterMembers.county'])->lockForUpdate()->whereKey($serviceDeskPolicy)->sole();
            abort_unless($policy->status === 'draft', 409, __('support-desk.policy.errors.draft_required'));
            abort_if($policy->created_by === $publisher->id, 403, __('support-desk.policy.errors.independent_publisher'));
            abort_unless($policy->businessCalendar->status === 'published' && $policy->businessCalendar->checksum !== null, 409, __('support-desk.policy.errors.published_calendar_required'));
            abort_unless($policy->businessCalendar->effective_from->lessThanOrEqualTo($policy->effective_from), 422, __('support-desk.policy.errors.calendar_effective_from'));
            if ($policy->businessCalendar->effective_to !== null) {
                abort_unless($policy->effective_to !== null && $policy->effective_to->lessThanOrEqualTo($policy->businessCalendar->effective_to), 422, __('support-desk.policy.errors.calendar_effective_to'));
            }
            abort_if(ServiceDeskPolicy::query()->where('code', $policy->code)->where('status', 'published')->whereKeyNot($policy)->where('effective_from', '<', $policy->effective_to ?? '9999-12-31')->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $policy->effective_from))->exists(), 409, __('support-desk.policy.errors.overlapping_versions'));

            $activeRoster = $policy->rosterMembers->filter(fn (ServiceDeskRosterMember $member): bool => $member->starts_at->lessThanOrEqualTo($policy->effective_from) && ($member->ends_at === null || $member->ends_at->greaterThan($policy->effective_from)));
            foreach ([1, 3] as $requiredTier) {
                abort_unless($activeRoster->contains(fn (ServiceDeskRosterMember $member): bool => $member->tier === $requiredTier && $member->county_id === null && $member->user->programmeRole()->hasNationalScope() && $member->user->can(ProgrammePermission::ResolveSupportTickets->value)), 422, __('support-desk.policy.errors.national_tier_required', ['tier' => $requiredTier]));
            }

            $authorityStatus = (string) $decision['authority_status'];
            $approvalReference = $authorityStatus === 'approved' ? trim((string) ($decision['approval_reference'] ?? '')) : null;
            abort_if($authorityStatus === 'approved' && $approvalReference === '', 422, __('support-desk.policy.errors.approval_reference_required'));
            $policy->forceFill(['authority_status' => $authorityStatus, 'approval_reference' => $approvalReference])->save();
            $checksum = $this->canonicalJson->checksum($policy->refresh()->canonicalPayload());
            $policy->update(['status' => 'published', 'published_by' => $publisher->id, 'published_at' => now(), 'checksum' => $checksum]);
            $this->auditLogger->record($publisher, $policy, 'support.policy.published', __('support-desk.policy.audit.published', ['code' => $policy->code, 'version' => $policy->version]), metadata: ['authority_status' => $authorityStatus, 'approval_reference' => $approvalReference, 'checksum' => $checksum, 'business_calendar_checksum' => $policy->businessCalendar->checksum]);

            return $policy->refresh()->load(['businessCalendar', 'rosterMembers.user', 'rosterMembers.county']);
        }, attempts: 3);
    }
}
