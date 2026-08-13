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

        abort_unless($policy !== null && $policy->checksum !== null, 503, __('support-desk.policy.errors.no_effective_policy'));
        $this->verifyPinned($policy, $policy->checksum);
        abort_unless($policy->businessCalendar->effective_from->lessThanOrEqualTo($at) && ($policy->businessCalendar->effective_to === null || $policy->businessCalendar->effective_to->greaterThan($at)), 503, __('support-desk.policy.errors.calendar_not_effective'));

        return $policy;
    }

    public function verifyPinned(ServiceDeskPolicy $policy, string $expectedChecksum): void
    {
        abort_unless($policy->status === 'published' && $policy->checksum !== null && hash_equals($expectedChecksum, $policy->checksum) && hash_equals($policy->checksum, $this->canonicalJson->checksum($policy->canonicalPayload())), 503, __('support-desk.policy.errors.integrity_failed'));
    }

    /** @return array{first_response: float, resolution: float, reminder: float} */
    public function target(ServiceDeskPolicy $policy, string $priority): array
    {
        $target = $policy->priority_targets[$priority] ?? null;
        abort_unless(is_array($target) && is_numeric($target['first_response'] ?? null) && is_numeric($target['resolution'] ?? null) && is_numeric($target['reminder'] ?? null), 503, __('support-desk.policy.errors.invalid_target', ['priority' => $priority]));

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
            abort_unless(is_int($tier) && $tier >= 1 && $tier <= 3, 503, __('support-desk.policy.errors.invalid_escalation_matrix'));

            return $tier;
        }

        abort(503, __('support-desk.policy.errors.missing_escalation_rule', ['priority' => $priority, 'stage' => $stage]));
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
