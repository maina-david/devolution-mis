<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AccessDelegation;
use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAccessDelegation
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $requester, array $attributes): AccessDelegation
    {
        return DB::transaction(function () use ($requester, $attributes): AccessDelegation {
            $beneficiary = User::query()->whereKey($attributes['beneficiary_id'])->lockForUpdate()->sole();
            abort_if($beneficiary->id === $requester->id, 403, __('security.delegation.errors.self_delegation'));
            abort_if($beneficiary->access_revoked_at !== null, 409, __('security.delegation.errors.suspended_beneficiary'));
            abort_unless($beneficiary->two_factor_confirmed_at !== null || $beneficiary->passkeys()->exists(), 409, __('security.delegation.errors.beneficiary_strong_authentication'));

            $permissionScope = $attributes['permission_scope'];
            abort_unless(is_array($permissionScope), 422, __('security.delegation.errors.permission_scope_list'));
            $permissions = collect(array_values(array_filter($permissionScope, fn (mixed $permission): bool => is_string($permission))))->map(fn (string $permission): string => ProgrammePermission::from($permission)->value)->unique()->sort()->values();
            $nonDelegable = [ProgrammePermission::ManageUserAccess->value, ProgrammePermission::ConfigurePlatform->value, ProgrammePermission::ManageSecurityGovernance->value, ProgrammePermission::CertifyAccess->value];
            abort_if($permissions->intersect($nonDelegable)->isNotEmpty(), 422, __('security.delegation.errors.non_delegable_permissions'));
            abort_unless($permissions->every(fn (string $permission): bool => $requester->hasPermissionTo($permission)), 403, __('security.delegation.errors.permanent_permissions_only'));

            $startsAt = CarbonImmutable::parse((string) $attributes['starts_at']);
            $expiresAt = CarbonImmutable::parse((string) $attributes['expires_at']);
            $maximumMinutes = $attributes['access_type'] === 'emergency' ? 240 : 60 * 24 * 90;
            abort_if($startsAt->diffInMinutes($expiresAt) > $maximumMinutes, 422, __($attributes['access_type'] === 'emergency' ? 'security.delegation.errors.emergency_duration' : 'security.delegation.errors.delegated_duration'));

            $counties = collect();
            $countyIds = [];
            if ($attributes['scope_type'] === 'county_portfolio') {
                $submittedCountyIds = $attributes['county_ids'];
                abort_unless(is_array($submittedCountyIds), 422, __('security.delegation.errors.invalid_county_scope'));
                foreach ($submittedCountyIds as $countyId) {
                    abort_unless(is_string($countyId), 422, __('security.delegation.errors.invalid_county_scope'));
                    $countyIds[] = $countyId;
                }
                $countyIds = array_values(array_unique($countyIds));
                $counties = County::query()->whereKey($countyIds)->orderBy('code')->get();
                abort_unless($counties->count() === count($countyIds), 422, __('security.delegation.errors.invalid_county_scope'));
                abort_unless($counties->every(fn (County $county): bool => $requester->canAccessCounty($county)), 403, __('security.delegation.errors.held_county_scope_only'));
            } else {
                abort_unless($requester->programmeRole()->hasNationalScope(), 403, __('security.delegation.errors.permanent_national_requester'));
            }

            $release = $this->referenceDataReleaseResolver->forAccessDelegation($countyIds, now());

            $delegation = AccessDelegation::create([
                ...$attributes,
                'requested_by' => $requester->id,
                'reference_data_release_id' => $release->id,
                'reference' => ($attributes['access_type'] === 'emergency' ? 'EAG-' : 'DAG-').now()->format('Y').'-'.mb_strtoupper(Str::random(8)),
                'permission_scope' => $permissions->all(),
                'county_scope_snapshot' => $counties->map->identityCell()->values()->all(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'pending',
            ]);
            $this->auditLogger->record($requester, $delegation, 'security.delegation.requested', __('security.delegation.audit.requested', ['reference' => $delegation->reference, 'beneficiary' => $beneficiary->name]), metadata: ['access_type' => $delegation->access_type, 'permissions' => $delegation->permission_scope, 'county_scope' => collect($delegation->county_scope_snapshot)->pluck('id')->all(), 'starts_at' => $startsAt->toIso8601String(), 'expires_at' => $expiresAt->toIso8601String(), 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $delegation->load(['requester', 'beneficiary']);
        });
    }
}
