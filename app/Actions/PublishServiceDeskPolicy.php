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
            abort_unless($policy->status === 'draft', 409, 'Only draft service-desk policies can be published.');
            abort_if($policy->created_by === $publisher->id, 403, 'Service-desk policy publication requires an actor independent of the author.');
            abort_unless($policy->businessCalendar->status === 'published' && $policy->businessCalendar->checksum !== null, 409, 'The selected business calendar must be published and checksum-bound.');
            abort_unless($policy->businessCalendar->effective_from->lessThanOrEqualTo($policy->effective_from), 422, 'The business calendar must be effective when the service policy commences.');
            if ($policy->businessCalendar->effective_to !== null) {
                abort_unless($policy->effective_to !== null && $policy->effective_to->lessThanOrEqualTo($policy->businessCalendar->effective_to), 422, 'A finite business calendar requires the service policy to end no later than the calendar expiry.');
            }
            abort_if(ServiceDeskPolicy::query()->where('code', $policy->code)->where('status', 'published')->whereKeyNot($policy)->where('effective_from', '<', $policy->effective_to ?? '9999-12-31')->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $policy->effective_from))->exists(), 409, 'Published versions of this service policy cannot overlap.');

            $activeRoster = $policy->rosterMembers->filter(fn (ServiceDeskRosterMember $member): bool => $member->starts_at->lessThanOrEqualTo($policy->effective_from) && ($member->ends_at === null || $member->ends_at->greaterThan($policy->effective_from)));
            foreach ([1, 3] as $requiredTier) {
                abort_unless($activeRoster->contains(fn (ServiceDeskRosterMember $member): bool => $member->tier === $requiredTier && $member->county_id === null && $member->user->programmeRole()->hasNationalScope() && $member->user->can(ProgrammePermission::ResolveSupportTickets->value)), 422, "An active nationally scoped tier {$requiredTier} resolver is required at policy commencement.");
            }

            $authorityStatus = (string) $decision['authority_status'];
            $approvalReference = $authorityStatus === 'approved' ? trim((string) ($decision['approval_reference'] ?? '')) : null;
            abort_if($authorityStatus === 'approved' && $approvalReference === '', 422, 'Approved policy publication requires an accountable approval reference.');
            $policy->forceFill(['authority_status' => $authorityStatus, 'approval_reference' => $approvalReference])->save();
            $checksum = $this->canonicalJson->checksum($policy->refresh()->canonicalPayload());
            $policy->update(['status' => 'published', 'published_by' => $publisher->id, 'published_at' => now(), 'checksum' => $checksum]);
            $this->auditLogger->record($publisher, $policy, 'support.policy.published', "Service-desk policy {$policy->code} v{$policy->version} independently published.", metadata: ['authority_status' => $authorityStatus, 'approval_reference' => $approvalReference, 'checksum' => $checksum, 'business_calendar_checksum' => $policy->businessCalendar->checksum]);

            return $policy->refresh()->load(['businessCalendar', 'rosterMembers.user', 'rosterMembers.county']);
        }, attempts: 3);
    }
}
