<?php

namespace Tests\Feature;

use App\Actions\CreateAccessDelegation;
use App\Enums\ProgrammePermission;
use App\Models\AccessDelegation;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\DelegatedAccessResolver;
use App\Services\ProgrammeCountyScope;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccessDelegationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_delegation_requires_strong_auth_maker_checker_and_expires_without_changing_permanent_rbac(): void
    {
        $this->freezeTime();
        $requester = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $approver = User::factory()->platformAdmin()->withTwoFactor()->create();
        $beneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $delegatedCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $release = $this->publishedReferenceRelease([$delegatedCounty], $requester);
        $payload = $this->payload($beneficiary, [$delegatedCounty], [ProgrammePermission::ManageProjects->value]);

        $this->assertFalse($beneficiary->can(ProgrammePermission::ManageProjects->value));
        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $payload)->assertRedirect();
        $delegation = AccessDelegation::query()->sole();
        $this->assertTrue(Str::isUuid($delegation->id));
        $this->assertSame($release->id, $delegation->reference_data_release_id);
        $this->assertSame($delegatedCounty->id, $delegation->county_scope_snapshot[0]['id']);
        $decision = ['decision' => 'approve', 'decision_rationale' => 'Independent approval confirms temporary project-management coverage and the least-privilege county scope.'];
        $this->actingAs($requester)->patch(route('security-governance.access-delegations.decide', [$delegation]), $decision)->assertForbidden();
        $this->actingAs($approver)->patch(route('security-governance.access-delegations.decide', [$delegation]), $decision)->assertRedirect();

        $this->assertSame('active', $delegation->refresh()->status);
        $this->assertNotNull($delegation->approval_checksum);
        $this->assertTrue($beneficiary->can(ProgrammePermission::ManageProjects->value));
        $this->assertContains(ProgrammePermission::ManageProjects->value, $beneficiary->programmePermissionValues());
        $this->assertTrue($beneficiary->canAccessCounty($delegatedCounty));
        $this->assertFalse($beneficiary->canAccessCounty($outsideCounty));
        $this->assertTrue(app(ProgrammeCountyScope::class)->query($beneficiary)->whereKey($delegatedCounty)->exists());
        $this->assertSame('county-official', $beneficiary->getRoleNames()->sole());

        $this->actingAs($requester)->patch(route('security-governance.access-delegations.revoke', [$delegation]), ['revocation_reason' => 'The temporary coverage assignment ended earlier than planned.'])->assertRedirect();
        $this->assertFalse($beneficiary->can(ProgrammePermission::ManageProjects->value));
        $this->assertFalse($beneficiary->canAccessCounty($delegatedCounty));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $delegation->id, 'action' => 'security.delegation.revoked']);
        $this->actingAs($requester)->get(route('security-governance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('security-governance/index')->has('delegations.data', 1)->where('delegations.data.0.reference', $delegation->reference)->has('delegationUsers')->has('delegablePermissions')->has('counties'));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($requester)->get(route('workspace.export', ['access-delegations', $format]))->assertOk()->assertDownload();
        }
    }

    public function test_emergency_access_is_four_hour_limited_scheduled_expired_and_fourth_actor_reviewed(): void
    {
        $this->freezeTime();
        Notification::fake();
        $requester = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $approver = User::factory()->platformAdmin()->withTwoFactor()->create();
        $reviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        $beneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $county = County::factory()->create();
        $this->publishedReferenceRelease([$county], $requester);
        $startsAt = now()->addMinute()->startOfSecond();
        $payload = $this->payload($beneficiary, [$county], [ProgrammePermission::ManageCitizenCases->value], 'emergency', $startsAt, $startsAt->copy()->addHours(4));

        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), [...$payload, 'expires_at' => $startsAt->copy()->addHours(5)->toIso8601String()])->assertSessionHasErrors('expires_at');
        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $payload)->assertRedirect();
        $delegation = AccessDelegation::query()->sole();
        $this->assertTrue($delegation->starts_at->isFuture(), "Stored start {$delegation->starts_at->toIso8601String()} is not after application time ".now()->toIso8601String());
        $this->actingAs($approver)->patch(route('security-governance.access-delegations.decide', [$delegation]), ['decision' => 'approve', 'decision_rationale' => 'Independent emergency approval is limited to the affected county, named incident and four-hour recovery window.'])->assertRedirect();
        $this->assertSame('scheduled', $delegation->refresh()->status);
        $this->assertFalse($beneficiary->can(ProgrammePermission::ManageCitizenCases->value));

        $this->travelTo($startsAt->copy()->addMinute());
        $this->assertSame(0, Artisan::call('security:reconcile-delegated-access'));
        app(DelegatedAccessResolver::class)->forget($beneficiary);
        $this->assertSame('active', $delegation->refresh()->status);
        $this->assertTrue($beneficiary->can(ProgrammePermission::ManageCitizenCases->value));
        Notification::assertSentTo($beneficiary, ProgrammeAlert::class, function (ProgrammeAlert $notification) use ($delegation): bool {
            app()->setLocale('sw');
            $content = $notification->toArray(new \stdClass);

            return $notification->titleTranslationKey === 'security.delegation.notifications.activated_title'
                && $content['title'] === __('security.delegation.notifications.activated_title')
                && $content['message'] === __('security.delegation.notifications.activated_message', ['reference' => $delegation->reference, 'expires_at' => $delegation->expires_at->toIso8601String()]);
        });

        $this->travelTo($startsAt->copy()->addHours(4)->addMinute());
        $this->assertSame(0, Artisan::call('security:reconcile-delegated-access'));
        app(DelegatedAccessResolver::class)->forget($beneficiary);
        $this->assertSame('review_pending', $delegation->refresh()->status);
        $this->assertFalse($beneficiary->can(ProgrammePermission::ManageCitizenCases->value));
        Notification::assertSentTo($beneficiary, ProgrammeAlert::class, function (ProgrammeAlert $notification) use ($delegation): bool {
            app()->setLocale('fr');
            $content = $notification->toArray(new \stdClass);

            return $notification->titleTranslationKey === 'security.delegation.notifications.emergency_expired_title'
                && $content['title'] === __('security.delegation.notifications.emergency_expired_title')
                && $content['message'] === __('security.delegation.notifications.expired_message', ['reference' => $delegation->reference]);
        });
        $review = ['post_use_outcome' => 'appropriate', 'post_use_findings' => 'Audit evidence shows the grant remained within the approved county, permission set and incident recovery purpose.'];
        $this->actingAs($approver)->patch(route('security-governance.access-delegations.review', [$delegation]), $review)->assertForbidden();
        $this->actingAs($reviewer)->patch(route('security-governance.access-delegations.review', [$delegation]), $review)->assertRedirect();
        $this->assertSame('reviewed', $delegation->refresh()->status);
        $this->assertSame($reviewer->id, $delegation->reviewed_by);
    }

    public function test_suspended_or_weak_identity_and_non_delegable_permissions_are_rejected(): void
    {
        $requester = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $weakBeneficiary = User::factory()->countyOfficial()->create();
        $county = County::factory()->create();
        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $this->payload($weakBeneficiary, [$county], [ProgrammePermission::ManageProjects->value]))->assertStatus(409);

        $strongBeneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $this->payload($strongBeneficiary, [$county], [ProgrammePermission::ManageSecurityGovernance->value]))->assertUnprocessable();
        $this->assertDatabaseCount('access_delegations', 0);
    }

    public function test_delegation_fail_closed_messages_and_catalogues_follow_the_active_locale(): void
    {
        $requester = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $weakBeneficiary = User::factory()->countyOfficial()->create();
        $county = County::factory()->create();
        $action = app(CreateAccessDelegation::class);

        app()->setLocale('sw');

        try {
            $action->handle($requester, $this->payload($weakBeneficiary, [$county], [ProgrammePermission::ManageProjects->value]));
            $this->fail('Delegation to an identity without strong authentication must fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(__('security.delegation.errors.beneficiary_strong_authentication'), $exception->getMessage());
        }

        app()->setLocale('fr');

        try {
            $action->handle($requester, $this->payload($requester, [$county], [ProgrammePermission::ManageProjects->value]));
            $this->fail('Self-delegation must fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(__('security.delegation.errors.self_delegation'), $exception->getMessage());
        }

        $english = require lang_path('en/security.php');
        $kiswahili = require lang_path('sw/security.php');
        $french = require lang_path('fr/security.php');

        foreach (['errors', 'audit', 'notifications', 'console'] as $section) {
            $this->assertSame(array_keys($english['delegation'][$section]), array_keys($kiswahili['delegation'][$section]));
            $this->assertSame(array_keys($english['delegation'][$section]), array_keys($french['delegation'][$section]));
        }
    }

    public function test_delegation_fails_closed_without_a_complete_effective_county_catalogue(): void
    {
        $this->freezeTime();
        $requester = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $beneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $payload = $this->payload($beneficiary, [$county], [ProgrammePermission::ManageProjects->value]);

        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $payload)->assertConflict();
        $this->publishedReferenceRelease([$otherCounty], $requester);
        $this->actingAs($requester)->post(route('security-governance.access-delegations.store'), $this->payload($beneficiary, [$county], [ProgrammePermission::ManageProjects->value]))->assertSessionHasErrors('county_ids');
        $this->assertDatabaseCount('access_delegations', 0);
    }

    public function test_delegated_access_is_memoized_per_scope_and_explicitly_invalidated_after_change(): void
    {
        $beneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $county = County::factory()->create();
        $delegation = AccessDelegation::factory()->create([
            'beneficiary_id' => $beneficiary->id,
            'scope_type' => 'county_portfolio',
            'permission_scope' => [ProgrammePermission::ManageProjects->value],
            'county_scope_snapshot' => [$county->identityCell()],
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
        ]);
        $resolver = app(DelegatedAccessResolver::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($resolver->allows($beneficiary, ProgrammePermission::ManageProjects->value));
        $this->assertTrue($resolver->allowsCounty($beneficiary, $county->id));
        $this->assertSame([$county->id], $resolver->countyIds($beneficiary));
        $this->assertCount(1, DB::getQueryLog());

        $delegation->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertTrue($resolver->allows($beneficiary, ProgrammePermission::ManageProjects->value));

        $resolver->forget($beneficiary);

        $this->assertFalse($resolver->allows($beneficiary, ProgrammePermission::ManageProjects->value));
        DB::disableQueryLog();
    }

    public function test_scoped_delegation_cache_is_discarded_at_the_long_running_worker_boundary(): void
    {
        $beneficiary = User::factory()->countyOfficial()->withTwoFactor()->create();
        $delegation = AccessDelegation::factory()->create([
            'beneficiary_id' => $beneficiary->id,
            'status' => 'active',
            'permission_scope' => [ProgrammePermission::ManageProjects->value],
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
        ]);
        $requestScopedResolver = app(DelegatedAccessResolver::class);
        $this->assertTrue($requestScopedResolver->allows($beneficiary, ProgrammePermission::ManageProjects->value));

        $delegation->update(['status' => 'revoked', 'revoked_at' => now(), 'revocation_reason' => 'The test simulates a grant changed by another process between worker requests.']);
        app()->forgetScopedInstances();
        $nextRequestResolver = app(DelegatedAccessResolver::class);

        $this->assertNotSame($requestScopedResolver, $nextRequestResolver);
        $this->assertFalse($nextRequestResolver->allows($beneficiary, ProgrammePermission::ManageProjects->value));
    }

    /** @param list<County> $counties
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function payload(User $beneficiary, array $counties, array $permissions, string $type = 'delegated', mixed $startsAt = null, mixed $expiresAt = null): array
    {
        $startsAt ??= now();
        $expiresAt ??= now()->addDay();

        return ['beneficiary_id' => $beneficiary->id, 'access_type' => $type, 'scope_type' => 'county_portfolio', 'permission_scope' => $permissions, 'county_ids' => collect($counties)->pluck('id')->all(), 'business_justification' => 'Temporary duty coverage is required to maintain an approved government service without expanding permanent access.', 'incident_reference' => $type === 'emergency' ? 'SEC-INC-2026-001' : null, 'compensating_controls' => $type === 'emergency' ? 'Continuous audit monitoring and a mandatory independent post-use review are enabled.' : null, 'starts_at' => $startsAt->toIso8601String(), 'expires_at' => $expiresAt->toIso8601String()];
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-ACCESS-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
