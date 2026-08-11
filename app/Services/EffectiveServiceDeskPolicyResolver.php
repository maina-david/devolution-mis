<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Models\ServiceDeskPolicy;
use App\Models\ServiceDeskRosterMember;
use App\Models\User;
use App\Support\CanonicalJson;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EffectiveServiceDeskPolicyResolver
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    public function resolve(CarbonInterface $at): ServiceDeskPolicy
    {
        $policy = ServiceDeskPolicy::query()
            ->with(['businessCalendar.holidays', 'rosterMembers.user', 'rosterMembers.county'])
            ->where('status', 'published')
            ->where('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->latest('version')
            ->first();

        abort_unless($policy !== null && $policy->checksum !== null, 503, 'No effective published service-desk policy is available.');
        $this->verifyPinned($policy, $policy->checksum);
        abort_unless($policy->businessCalendar->effective_from->lessThanOrEqualTo($at) && ($policy->businessCalendar->effective_to === null || $policy->businessCalendar->effective_to->greaterThan($at)), 503, 'The effective service-desk business calendar has expired or is not yet effective.');

        return $policy;
    }

    public function verifyPinned(ServiceDeskPolicy $policy, string $expectedChecksum): void
    {
        abort_unless($policy->status === 'published' && $policy->checksum !== null && hash_equals($expectedChecksum, $policy->checksum) && hash_equals($policy->checksum, $this->canonicalJson->checksum($policy->canonicalPayload())), 503, 'The pinned service-desk policy failed its integrity check.');
    }

    /** @return array{first_response: float, resolution: float, reminder: float} */
    public function target(ServiceDeskPolicy $policy, string $priority): array
    {
        $target = $policy->priority_targets[$priority] ?? null;
        abort_unless(is_array($target) && is_numeric($target['first_response'] ?? null) && is_numeric($target['resolution'] ?? null) && is_numeric($target['reminder'] ?? null), 503, "The {$priority} service target is invalid.");

        return [
            'first_response' => (float) $target['first_response'],
            'resolution' => (float) $target['resolution'],
            'reminder' => (float) $target['reminder'],
        ];
    }

    public function escalationTier(ServiceDeskPolicy $policy, string $priority, string $stage): int
    {
        foreach ($policy->escalation_rules as $rule) {
            if (($rule['priority'] ?? null) !== $priority || ($rule['stage'] ?? null) !== $stage) {
                continue;
            }

            $tier = $rule['tier'] ?? null;
            abort_unless(is_int($tier) && $tier >= 1 && $tier <= 3, 503, 'The pinned service-desk escalation matrix is invalid.');

            return $tier;
        }

        abort(503, "The pinned service-desk policy has no {$priority} {$stage} escalation rule.");
    }

    /** @return Collection<int, User> */
    public function recipients(ServiceDeskPolicy $policy, ?string $countyId, CarbonInterface $at, ?int $tier = null): Collection
    {
        return $policy->rosterMembers
            ->filter(fn (ServiceDeskRosterMember $member): bool => ($member->county_id === null || $member->county_id === $countyId)
                && $member->starts_at->lessThanOrEqualTo($at)
                && ($member->ends_at === null || $member->ends_at->greaterThan($at))
                && ($tier === null || $member->tier === $tier)
                && $member->user->access_revoked_at === null
                && $member->user->can(ProgrammePermission::ResolveSupportTickets->value))
            ->map(fn (ServiceDeskRosterMember $member): User => $member->user)
            ->unique('id')
            ->values();
    }
}
