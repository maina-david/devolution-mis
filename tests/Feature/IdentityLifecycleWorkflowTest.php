<?php

namespace Tests\Feature;

use App\Actions\CreateIdentityLifecycleRequest;
use App\Actions\DecideIdentityLifecycleRequest;
use App\Console\Commands\ApplyDueIdentityLifecycleRequests;
use App\Enums\ProgrammePermission;
use App\Models\AccessDelegation;
use App\Models\County;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use App\Services\DelegatedAccessResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentityLifecycleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_decision_applies_a_source_referenced_mover_event(): void
    {
        $homeCounty = County::factory()->create();
        $portfolioCounty = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $target = User::factory()->countyAdmin($homeCounty)->withTwoFactor()->create();

        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'mover', 'assessor', assignedCountyIds: [$portfolioCounty->id]));

        $this->assertTrue(Str::isUuid($request->id));
        $this->assertSame('county-admin', $request->current_access_snapshot['role']);
        $this->assertSame(64, strlen($request->source_checksum));
        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The independently verified HR assignment moves this officer into the assessment portfolio.']);

        $this->assertSame('assessor', $target->refresh()->programmeRole()->value);
        $this->assertNull($target->county_id);
        $this->assertTrue($target->assignedCounties()->whereKey($portfolioCounty)->exists());
        $this->assertSame('applied', $request->refresh()->status);
        $this->assertSame(64, strlen((string) $request->evidence_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $request->id, 'action' => 'security.identity-lifecycle.decided']);
    }

    public function test_leaver_event_revokes_roles_portfolios_and_live_sessions(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $target->assignedCounties()->attach($county);
        $delegation = AccessDelegation::factory()->create([
            'beneficiary_id' => $target->id,
            'approved_by' => $decider->id,
            'status' => 'active',
            'scope_type' => 'county_portfolio',
            'permission_scope' => [ProgrammePermission::ManageProjects->value],
            'county_scope_snapshot' => [$county->identityCell()],
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
        ]);
        $delegatedAccess = app(DelegatedAccessResolver::class);
        $this->assertTrue($delegatedAccess->allows($target, ProgrammePermission::ManageProjects->value));
        DB::table((string) config('session.table'))->insert(['id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'JML test', 'payload' => '', 'last_activity' => now()->timestamp]);
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'leaver'));
        $this->assertSame([$delegation->id], $request->current_access_snapshot['delegated_access_ids']);

        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The authoritative separation event has been checked against the attached HR exit reference.']);

        $this->assertNotNull($target->refresh()->access_revoked_at);
        $this->assertSame(0, $target->roles()->count());
        $this->assertSame(0, $target->assignedCounties()->count());
        $this->assertSame(0, DB::table((string) config('session.table'))->where('user_id', $target->id)->count());
        $this->assertSame(1, $request->refresh()->sessions_revoked);
        $this->assertSame('revoked', $delegation->refresh()->status);
        $this->assertFalse($delegatedAccess->allows($target, ProgrammePermission::ManageProjects->value));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $delegation->id, 'action' => 'security.delegation.revoked']);
    }

    public function test_requester_cannot_approve_and_terminal_evidence_is_immutable(): void
    {
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'leaver'));

        try {
            app(DecideIdentityLifecycleRequest::class)->handle($request, $requester, ['decision' => 'approve', 'rationale' => 'This must fail because maker and checker are the same identity.']);
            $this->fail('Expected maker-checker denial.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'reject', 'rationale' => 'The source event could not be corroborated against the stated HR evidence reference.']);
        $this->expectExceptionMessage('Terminal identity lifecycle evidence is immutable');
        IdentityLifecycleRequest::query()->whereKey($request->id)->update(['business_reason' => 'Tampered reason']);
    }

    public function test_http_workflow_enforces_permissions_validation_uniqueness_and_scoped_representation(): void
    {
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();
        $target = User::factory()->assessor()->create();
        $payload = $this->payload($target, 'leaver');

        $this->actingAs($countyUser)->post(route('security-governance.identity-lifecycle.store', $countyUser->currentTeam->slug), $payload)->assertForbidden();
        $this->actingAs($requester)->post(route('security-governance.identity-lifecycle.store', $requester->currentTeam->slug), $payload)->assertRedirect();
        $request = IdentityLifecycleRequest::query()->sole();
        $this->actingAs($requester)->post(route('security-governance.identity-lifecycle.store', $requester->currentTeam->slug), $payload)->assertSessionHasErrors('source_event_id');
        $this->actingAs($requester)->patch(route('security-governance.identity-lifecycle.decide', [$requester->currentTeam->slug, $request]), ['decision' => 'approve', 'rationale' => 'The source event has been independently matched to the authoritative workforce record.'])->assertForbidden();
        $this->actingAs($decider)->patch(route('security-governance.identity-lifecycle.decide', [$decider->currentTeam->slug, $request]), ['decision' => 'approve', 'rationale' => 'The source event has been independently matched to the authoritative workforce record.'])->assertRedirect();
        $this->actingAs($decider)->get(route('security-governance.index', $decider->currentTeam->slug))->assertInertia(fn (Assert $page) => $page->component('security-governance/index')->has('identityLifecycle.data', 1)->where('identityLifecycle.data.0.sourceEventId', $payload['source_event_id'])->where('identityLifecycle.data.0.status', 'applied')->where('capabilities.certify', true));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($decider)->get(route('workspace.export', [$decider->currentTeam->slug, 'identity-lifecycle', $format]))->assertOk()->assertDownload();
        }

        $invalidMover = $this->payload(User::factory()->assessor()->create(), 'mover', 'county-admin');
        $this->actingAs($requester)->post(route('security-governance.identity-lifecycle.store', $requester->currentTeam->slug), $invalidMover)->assertSessionHasErrors('proposed_home_county_id');
    }

    public function test_future_effective_approval_is_applied_once_by_an_authorized_service_identity(): void
    {
        $this->travelTo('2026-08-10 10:00:00');
        $homeCounty = County::factory()->create();
        $portfolioCounty = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $service = User::factory()->platformAdmin()->create();
        $target = User::factory()->countyAdmin($homeCounty)->withTwoFactor()->create();
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'mover', 'assessor', assignedCountyIds: [$portfolioCounty->id], effectiveAt: now()->addHour()->toIso8601String()));

        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The future-dated transfer was independently reconciled to the authoritative workforce instruction.']);
        $this->assertSame('approved', $request->refresh()->status);
        $this->assertSame('county-admin', $target->refresh()->programmeRole()->value);
        $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('inactive')->assertSuccessful();
        config()->set('security-governance.identity_lifecycle_service_user_email', User::factory()->countyAdmin()->create()->email);
        $this->artisan('security:apply-due-identity-lifecycle')->assertFailed();
        config()->set('security-governance.identity_lifecycle_service_user_email', $service->email);
        $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('Applied 0')->assertSuccessful();

        $this->travelTo('2026-08-10 11:01:00');
        $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('Applied 1')->assertSuccessful();
        $this->assertSame('applied', $request->refresh()->status);
        $this->assertSame(1, $request->application_attempts);
        $this->assertSame($service->id, $request->applied_by);
        $this->assertSame('assessor', $target->refresh()->programmeRole()->value);
        $this->assertTrue($target->assignedCounties()->whereKey($portfolioCounty)->exists());
        $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('Applied 0')->assertSuccessful();
        $this->assertSame(1, $request->refresh()->application_attempts);
    }

    public function test_snapshot_drift_is_a_visible_retryable_exception_before_any_access_change(): void
    {
        $this->travelTo('2026-08-10 10:00:00');
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $service = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'leaver', effectiveAt: now()->addMinute()->toIso8601String()));
        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The separation event was independently verified against the authoritative exit instruction.']);
        $target->assignedCounties()->attach($county);
        config()->set('security-governance.identity_lifecycle_service_user_email', $service->email);

        $this->travelTo('2026-08-10 10:02:00');
        $this->artisan('security:apply-due-identity-lifecycle')->assertFailed();
        $this->assertSame('application_exception', $request->refresh()->status);
        $this->assertSame('access_snapshot_drift', $request->application_error_code);
        $this->assertNull($target->refresh()->access_revoked_at);
        $target->assignedCounties()->detach();
        $this->artisan('security:apply-due-identity-lifecycle')->assertSuccessful();
        $this->assertSame('applied', $request->refresh()->status);
        $this->assertSame(2, $request->application_attempts);
        $this->assertNotNull($target->refresh()->access_revoked_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $request->id, 'action' => 'security.identity-lifecycle.application-exception']);
    }

    public function test_delegated_access_drift_stops_application_until_the_approved_snapshot_is_restored(): void
    {
        $this->travelTo('2026-08-10 10:00:00');
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $service = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'leaver', effectiveAt: now()->addMinute()->toIso8601String()));
        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The separation event was independently verified before its effective time.']);
        $delegation = AccessDelegation::factory()->create([
            'beneficiary_id' => $target->id,
            'approved_by' => $decider->id,
            'status' => 'active',
            'permission_scope' => [ProgrammePermission::ViewProjects->value],
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
        ]);
        config()->set('security-governance.identity_lifecycle_service_user_email', $service->email);

        $this->travelTo('2026-08-10 10:02:00');
        $this->artisan('security:apply-due-identity-lifecycle')->assertFailed();
        $this->assertSame('application_exception', $request->refresh()->status);
        $this->assertSame('access_snapshot_drift', $request->application_error_code);
        $this->assertNull($target->refresh()->access_revoked_at);
        $this->assertSame('active', $delegation->status);

        $delegation->update(['status' => 'revoked', 'revoked_by' => $decider->id, 'revoked_at' => now(), 'revocation_reason' => 'The post-approval grant was withdrawn so the approved access snapshot can be restored.']);
        $this->artisan('security:apply-due-identity-lifecycle')->assertSuccessful();
        $this->assertSame('applied', $request->refresh()->status);
        $this->assertNotNull($target->refresh()->access_revoked_at);
    }

    public function test_duplicate_runner_invocation_skips_safely_without_attempting_due_events(): void
    {
        $this->travelTo('2026-08-10 10:00:00');
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $service = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $request = app(CreateIdentityLifecycleRequest::class)->handle($requester, $this->payload($target, 'leaver', effectiveAt: now()->addMinute()->toIso8601String()));
        app(DecideIdentityLifecycleRequest::class)->handle($request, $decider, ['decision' => 'approve', 'rationale' => 'The independently verified separation is due for scheduled reconciliation.']);
        config()->set('security-governance.identity_lifecycle_service_user_email', $service->email);
        $this->travelTo('2026-08-10 10:02:00');

        $runnerLock = Cache::lock(ApplyDueIdentityLifecycleRequests::RunnerLock, 300);
        $this->assertTrue($runnerLock->get());
        try {
            $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('already running')->assertSuccessful();
            $this->assertSame('approved', $request->refresh()->status);
            $this->assertSame(0, $request->application_attempts);
            $this->assertNull($target->refresh()->access_revoked_at);
        } finally {
            $runnerLock->release();
        }

        $this->artisan('security:apply-due-identity-lifecycle')->expectsOutputToContain('Applied 1')->assertSuccessful();
        $this->assertSame('applied', $request->refresh()->status);
        $this->assertSame(1, $request->application_attempts);
    }

    /** @param list<string> $assignedCountyIds
     * @return array{source_system:string, source_event_id:string, source_evidence_reference:string, event_type:string, user_id:string, effective_at:string, proposed_role:string|null, proposed_home_county_id:string|null, proposed_assigned_county_ids:list<string>, business_reason:string}
     */
    private function payload(User $target, string $eventType, ?string $role = null, ?string $homeCountyId = null, array $assignedCountyIds = [], ?string $effectiveAt = null): array
    {
        return [
            'source_system' => 'IPPD-HRIS',
            'source_event_id' => 'HR-'.Str::uuid(),
            'source_evidence_reference' => 'DMS-HR-JML-2026-001',
            'event_type' => $eventType,
            'user_id' => $target->id,
            'effective_at' => $effectiveAt ?? now()->subMinute()->toIso8601String(),
            'proposed_role' => $role,
            'proposed_home_county_id' => $homeCountyId,
            'proposed_assigned_county_ids' => $assignedCountyIds,
            'business_reason' => 'The authoritative HR event changes the officer assignment and requires controlled reconciliation of IDMIS access.',
        ];
    }
}
