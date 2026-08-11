<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\AccessDelegation;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateIdentityLifecycleRequest
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{source_system:string, source_event_id:string, source_evidence_reference:string, event_type:string, user_id:string, effective_at:string, proposed_role?:string|null, proposed_home_county_id?:string|null, proposed_assigned_county_ids?:list<string>, business_reason:string} $attributes */
    public function handle(User $actor, array $attributes): IdentityLifecycleRequest
    {
        return DB::transaction(function () use ($actor, $attributes): IdentityLifecycleRequest {
            $user = User::query()->with(['roles:id,name', 'assignedCounties:id'])->lockForUpdate()->findOrFail($attributes['user_id']);
            $eventType = $attributes['event_type'];
            abort_if($eventType === 'joiner' && $user->access_revoked_at === null, 409, 'A joiner event can only restore a suspended pre-provisioned identity.');
            abort_if(in_array($eventType, ['mover', 'leaver'], true) && $user->access_revoked_at !== null, 409, 'Mover and leaver events require an active identity.');

            $proposedRole = $eventType === 'leaver' ? null : UserRole::from((string) ($attributes['proposed_role'] ?? null));
            $assignedCountyIds = $proposedRole?->hasAssignedCountyScope() ? ($attributes['proposed_assigned_county_ids'] ?? []) : [];
            $homeCountyId = in_array($proposedRole, [UserRole::CountyOfficial, UserRole::CountyAdmin], true) ? ($attributes['proposed_home_county_id'] ?? null) : null;
            $snapshot = [
                'role' => $user->roles()->value('name'),
                'home_county_id' => $user->county_id,
                'assigned_county_ids' => $user->assignedCounties->pluck('id')->sort()->values()->all(),
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
            $sourcePayload = ['source_system' => $attributes['source_system'], 'source_event_id' => $attributes['source_event_id'], 'source_evidence_reference' => $attributes['source_evidence_reference'], 'event_type' => $eventType, 'user_id' => $user->id, 'effective_at' => $attributes['effective_at'], 'proposed_role' => $proposedRole?->value, 'proposed_home_county_id' => $homeCountyId, 'proposed_assigned_county_ids' => $assignedCountyIds];

            $request = IdentityLifecycleRequest::create([...$sourcePayload, 'source_checksum' => hash('sha256', json_encode($sourcePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 'current_access_snapshot' => $snapshot, 'business_reason' => $attributes['business_reason'], 'requested_by' => $actor->id]);
            $this->auditLogger->record($actor, $request, 'security.identity-lifecycle.requested', "{$eventType} identity lifecycle change requested from {$attributes['source_system']}.", $user->county_id, ['source_event_id' => $attributes['source_event_id'], 'target_user_id' => $user->id]);

            return $request;
        });
    }
}
