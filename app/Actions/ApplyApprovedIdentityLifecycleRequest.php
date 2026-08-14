<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\AccessDelegation;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\DelegatedAccessResolver;
use App\Services\ProgrammeAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyApprovedIdentityLifecycleRequest
{
    public function __construct(
        private ProgrammeAuthorization $authorization,
        private AuditLogger $auditLogger,
        private DelegatedAccessResolver $delegatedAccess,
    ) {}

    public function handle(IdentityLifecycleRequest $request, User $actor, string $trigger): bool
    {
        abort_unless($actor->access_revoked_at === null && $actor->can(ProgrammePermission::ManageSecurityGovernance->value), 403, __('security.identity_lifecycle.errors.application_unauthorized'));

        return DB::transaction(function () use ($request, $actor, $trigger): bool {
            $request = IdentityLifecycleRequest::query()->with(['user.roles', 'user.assignedCounties'])->lockForUpdate()->findOrFail($request->id);
            if (! in_array($request->status, ['approved', 'application_exception'], true) || $request->effective_at->isFuture()) {
                return false;
            }

            $user = User::query()->with(['roles:id,name', 'assignedCounties:id'])->lockForUpdate()->findOrFail($request->user_id);
            $attempts = $request->application_attempts + 1;
            $liveSnapshot = $this->normalizedAccessSnapshot($user);
            $approvedSnapshot = $this->normalizeStoredSnapshot($request->current_access_snapshot);
            if ($liveSnapshot !== $approvedSnapshot) {
                $this->recordException($request, $actor, $attempts, 'access_snapshot_drift', $trigger);

                return false;
            }

            if ($request->event_type !== 'leaver') {
                $role = UserRole::from((string) $request->proposed_role);
                if (in_array($role->value, config('security-governance.privileged_roles'), true) && $user->two_factor_confirmed_at === null && ! $user->passkeys()->exists()) {
                    $this->recordException($request, $actor, $attempts, 'strong_auth_missing', $trigger);

                    return false;
                }
            }

            $revocations = $this->applyAccess($request, $user, $actor);
            $appliedEvidence = ['request_id' => $request->id, 'source_checksum' => $request->source_checksum, 'approval_checksum' => $request->evidence_checksum, 'applied_by' => $actor->id, 'trigger' => $trigger, 'attempt' => $attempts, ...$revocations];
            $request->update(['status' => 'applied', 'applied_at' => now(), 'applied_by' => $actor->id, 'application_attempts' => $attempts, 'last_application_attempt_at' => now(), 'application_error_code' => null, 'sessions_revoked' => $revocations['sessions_revoked'], 'evidence_checksum' => hash('sha256', json_encode($appliedEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $this->auditLogger->record($actor, $request, 'security.identity-lifecycle.applied', __('security.identity_lifecycle.audit.applied', ['event' => __("security.identity_lifecycle.values.events.{$request->event_type}"), 'trigger' => __("security.identity_lifecycle.values.triggers.{$trigger}")]), $user->county_id, ['source_event_id' => $request->source_event_id, 'target_user_id' => $user->id, ...$revocations, 'application_attempt' => $attempts]);
            $user->notify(ProgrammeAlert::translated('security.identity_lifecycle.notifications.access_reconciled_title', "security.identity_lifecycle.notifications.access_reconciled_message.{$request->event_type}", 'security-governance', messageParameters: ['reference' => $request->source_event_id]));

            return true;
        });
    }

    /** @return array{role:string|null, home_county_id:string|null, assigned_county_ids:list<string>, delegated_access_ids:list<string>, access_revoked_at:string|null} */
    private function normalizedAccessSnapshot(User $user): array
    {
        $role = $user->roles()->value('name');

        return [
            'role' => is_string($role) ? $role : null,
            'home_county_id' => $user->county_id,
            'assigned_county_ids' => array_values($user->assignedCounties->pluck('id')->map(static fn (mixed $id): string => (string) $id)->sort()->all()),
            'delegated_access_ids' => array_values(AccessDelegation::query()
                ->where('beneficiary_id', $user->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all()),
            'access_revoked_at' => $user->access_revoked_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{role?:mixed, home_county_id?:mixed, assigned_county_ids?:mixed, delegated_access_ids?:mixed, access_revoked_at?:mixed}  $snapshot
     * @return array{role:string|null, home_county_id:string|null, assigned_county_ids:list<string>, delegated_access_ids:list<string>, access_revoked_at:string|null}
     */
    private function normalizeStoredSnapshot(array $snapshot): array
    {
        $assignedCountyIds = is_array($snapshot['assigned_county_ids'] ?? null) ? $snapshot['assigned_county_ids'] : [];
        $assignedCountyIds = array_values(collect($assignedCountyIds)->map(static fn (mixed $id): string => (string) $id)->sort()->all());
        $delegatedAccessIds = is_array($snapshot['delegated_access_ids'] ?? null) ? $snapshot['delegated_access_ids'] : [];
        $delegatedAccessIds = array_values(collect($delegatedAccessIds)->map(static fn (mixed $id): string => (string) $id)->sort()->all());

        return [
            'role' => isset($snapshot['role']) ? (string) $snapshot['role'] : null,
            'home_county_id' => isset($snapshot['home_county_id']) ? (string) $snapshot['home_county_id'] : null,
            'assigned_county_ids' => $assignedCountyIds,
            'delegated_access_ids' => $delegatedAccessIds,
            'access_revoked_at' => isset($snapshot['access_revoked_at']) ? (string) $snapshot['access_revoked_at'] : null,
        ];
    }

    private function recordException(IdentityLifecycleRequest $request, User $actor, int $attempts, string $code, string $trigger): void
    {
        $request->update(['status' => 'application_exception', 'application_attempts' => $attempts, 'last_application_attempt_at' => now(), 'application_error_code' => $code]);
        $this->auditLogger->record($actor, $request, 'security.identity-lifecycle.application-exception', __('security.identity_lifecycle.audit.application_exception', ['exception' => __("security.identity_lifecycle.values.exceptions.{$code}")]), metadata: ['trigger' => $trigger, 'application_attempt' => $attempts]);
    }

    /** @return array{sessions_revoked:int, delegated_access_revoked:int} */
    private function applyAccess(IdentityLifecycleRequest $request, User $user, User $actor): array
    {
        if ($request->event_type === 'leaver') {
            $sessions = DB::table((string) config('session.table'))->where('user_id', $user->id);
            $sessionsRevoked = $sessions->count();
            $sessions->delete();
            $delegations = AccessDelegation::query()
                ->where('beneficiary_id', $user->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->lockForUpdate()
                ->get();
            foreach ($delegations as $delegation) {
                $delegation->update(['status' => 'revoked', 'revoked_by' => $actor->id, 'revoked_at' => now(), 'revocation_reason' => __('security.identity_lifecycle.revocation_reason', ['reference' => $request->source_event_id])]);
                $this->auditLogger->record($actor, $delegation, 'security.delegation.revoked', __('security.identity_lifecycle.audit.delegation_revoked', ['reference' => $delegation->reference]), metadata: ['identity_lifecycle_request_id' => $request->id, 'source_event_id' => $request->source_event_id]);
            }
            $user->syncRoles([]);
            $user->assignedCounties()->detach();
            $user->forceFill(['county_id' => null, 'access_revoked_at' => now(), 'access_revoked_by' => $actor->id, 'access_revocation_reason' => $request->business_reason, 'remember_token' => Str::random(60)])->save();
            $this->delegatedAccess->forget($user);

            return ['sessions_revoked' => $sessionsRevoked, 'delegated_access_revoked' => $delegations->count()];
        }

        $role = UserRole::from((string) $request->proposed_role);
        $this->authorization->assignRole($user, $role);
        $user->assignedCounties()->sync($request->proposed_assigned_county_ids);
        $user->forceFill(['county_id' => $request->proposed_home_county_id, 'access_revoked_at' => null, 'access_revoked_by' => null, 'access_revocation_reason' => null, 'remember_token' => Str::random(60)])->save();
        $this->delegatedAccess->forget($user);

        return ['sessions_revoked' => 0, 'delegated_access_revoked' => 0];
    }
}
