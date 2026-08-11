<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\ServiceDeskPolicy;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateServiceDeskPolicy
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $author, array $attributes): ServiceDeskPolicy
    {
        $code = mb_strtoupper((string) $attributes['code']);

        $policy = DB::transaction(function () use ($author, $attributes, $code): ServiceDeskPolicy {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["service-desk-policy:{$code}"]);
            $policy = ServiceDeskPolicy::create([
                ...collect($attributes)->except('roster')->all(),
                'code' => $code,
                'version' => ((int) ServiceDeskPolicy::query()->withTrashed()->where('code', $code)->max('version')) + 1,
                'authority_status' => 'provisional',
                'approval_reference' => null,
                'status' => 'draft',
                'created_by' => $author->id,
            ]);

            foreach ((array) $attributes['roster'] as $rosterAttributes) {
                abort_unless(is_array($rosterAttributes), 422, 'Roster entries must be structured records.');
                $member = User::query()->findOrFail((string) $rosterAttributes['user_id']);
                abort_unless($member->can(ProgrammePermission::ResolveSupportTickets->value), 422, "{$member->name} is not authorized to resolve service-desk tickets.");
                $policy->rosterMembers()->create([...$rosterAttributes, 'created_by' => $author->id]);
            }

            $this->auditLogger->record($author, $policy, 'support.policy.created', "Service-desk policy {$code} v{$policy->version} drafted.", metadata: ['business_calendar_id' => $policy->business_calendar_id, 'roster_count' => count((array) $attributes['roster'])]);

            return $policy;
        }, attempts: 3);

        return $policy->load(['businessCalendar', 'rosterMembers.user', 'rosterMembers.county']);
    }
}
