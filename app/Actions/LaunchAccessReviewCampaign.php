<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AccessReviewCampaign;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LaunchAccessReviewCampaign
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{reviewer_id: string, reference: string, name: string, scope: string, role_scope: list<string>, period_from: string, period_to: string, due_at: string} $attributes */
    public function handle(User $launcher, array $attributes): AccessReviewCampaign
    {
        return DB::transaction(function () use ($launcher, $attributes): AccessReviewCampaign {
            abort_if($launcher->id === $attributes['reviewer_id'], 403, 'The campaign launcher cannot be its independent reviewer.');
            $reviewer = User::query()->findOrFail($attributes['reviewer_id']);
            abort_unless($reviewer->can(ProgrammePermission::CertifyAccess->value), 422, 'The selected reviewer is not authorized to certify access.');
            $users = User::query()->whereNull('access_revoked_at')->whereHas('roles', fn ($query) => $query->whereIn('name', $attributes['role_scope']))->with(['roles.permissions', 'assignedCounties:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'county:id,name'])->orderBy('name')->get();
            abort_if($users->isEmpty(), 422, 'No active users match the selected role scope.');

            $lastAuthenticated = DB::table((string) config('session.table'))->whereIn('user_id', $users->pluck('id'))->selectRaw('user_id, MAX(last_activity) AS last_activity')->groupBy('user_id')->pluck('last_activity', 'user_id');
            $campaign = AccessReviewCampaign::create([...$attributes, 'launched_by' => $launcher->id, 'status' => 'open', 'launched_at' => now(), 'item_count' => $users->count()]);
            foreach ($users as $user) {
                $role = $user->getRoleNames()->firstOrFail();
                $lastActivity = $lastAuthenticated->get($user->id);
                $campaign->items()->create(['user_id' => $user->id, 'role_name' => $role, 'permission_snapshot' => $user->programmePermissionValues(), 'home_county_id' => $user->county_id, 'assigned_county_snapshot' => $user->assignedCounties->map->identityCell()->values()->all(), 'mfa_enabled' => $user->two_factor_confirmed_at !== null, 'passkey_enabled' => $user->passkeys()->exists(), 'last_authenticated_at' => $lastActivity ? CarbonImmutable::createFromTimestamp((int) $lastActivity) : null]);
            }
            $this->auditLogger->record($launcher, $campaign, 'security.access-review.launched', "Access review {$campaign->reference} launched for {$campaign->item_count} identities.");

            return $campaign->load('items');
        });
    }
}
