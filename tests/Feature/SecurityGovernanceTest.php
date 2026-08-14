<?php

namespace Tests\Feature;

use App\Actions\DecideAccessReviewItem;
use App\Actions\LaunchAccessReviewCampaign;
use App\Actions\ReinstateUserAccess;
use App\Actions\ReviewSecurityThreat;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\SecurityThreat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_threat_register_calculates_risk_and_requires_independent_review(): void
    {
        app()->setLocale('sw');
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();
        $this->actingAs($countyUser)->get(route('security-governance.index'))->assertForbidden();
        $this->actingAs($author)->post(route('security-governance.threats.store'), $this->threatPayload($reviewer))->assertRedirect();
        $threat = SecurityThreat::query()->sole();
        $this->assertTrue(Str::isUuid($threat->id));
        $this->assertSame(20, $threat->inherent_risk_score);
        $this->assertSame(['login', 'privileged_exports', 'integration_credentials'], $threat->entry_points);
        $review = ['decision' => 'accepted', 'treatment_status' => 'mitigated', 'residual_likelihood' => 2, 'residual_impact' => 4, 'review_note' => 'MFA, certification, export auditing and source-owner secret controls reduce likelihood while residual disclosure impact remains material.', 'evidence_references' => 'SEC-TEST-001, ACCESS-TEST-001'];
        $this->actingAs($author)->patch(route('security-governance.threats.review', [$threat]), $review)->assertForbidden();
        $this->actingAs($reviewer)->patch(route('security-governance.threats.review', [$threat]), $review)->assertRedirect();
        $this->assertSame('accepted', $threat->refresh()->status);
        $this->assertSame(8, $threat->residual_risk_score);
        $this->assertStringContainsString('Tathmini huru:', $threat->treatment_plan);
        $this->assertSame(
            [
                "Tishio {$threat->reference} limewasilishwa kwa tathmini huru.",
                "Tishio {$threat->reference} limepata uamuzi accepted na alama ya hatari iliyobaki 8.",
            ],
            AuditEvent::query()->where('subject_id', $threat->id)->orderBy('occurred_at')->pluck('description')->all(),
        );
        $threat->delete();
        $this->assertSoftDeleted($threat);
    }

    public function test_threat_review_guards_follow_the_active_locale(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        app()->setLocale('fr');
        $review = [
            'decision' => 'accepted',
            'treatment_status' => 'mitigated',
            'residual_likelihood' => 2,
            'residual_impact' => 2,
            'review_note' => 'Les contrôles ont été vérifiés indépendamment avec des preuves conservées.',
            'evidence_references' => 'SEC-REVIEW-001',
        ];
        $this->assertThreatReviewRejected(SecurityThreat::factory()->create([
            'submitted_by' => $author->id,
            'status' => 'accepted',
        ]), $reviewer, $review, 'security.threat_review.errors.submitted_only');

        $this->assertThreatReviewRejected(SecurityThreat::factory()->create([
            'submitted_by' => $author->id,
        ]), $author, $review, 'security.threat_review.errors.independent_reviewer_required');

        $this->assertThreatReviewRejected(SecurityThreat::factory()->create([
            'submitted_by' => $author->id,
            'inherent_risk_score' => 4,
        ]), $reviewer, [
            ...$review,
            'residual_likelihood' => 3,
            'residual_impact' => 3,
        ], 'security.threat_review.errors.residual_exceeds_inherent');
    }

    public function test_access_campaign_snapshots_scope_and_blocks_privileged_retention_without_strong_authentication(): void
    {
        app()->setLocale('sw');
        $launcher = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $reviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        $target = User::factory()->countyAdmin()->create();
        $this->actingAs($launcher)->post(route('security-governance.access-reviews.store'), $this->campaignPayload($reviewer, ['county-admin']))->assertRedirect();
        $campaign = AccessReviewCampaign::query()->sole();
        $item = AccessReviewItem::query()->sole();
        $this->assertTrue(Str::isUuid($campaign->id));
        $this->assertSame($target->id, $item->user_id);
        $this->assertSame('county-admin', $item->role_name);
        $this->assertFalse($item->mfa_enabled);
        $decision = ['decision' => 'retain', 'rationale' => 'County administration access remains necessary for the assigned county and current duties.'];
        $this->actingAs($reviewer)->patch(route('security-governance.access-review-items.decide', [$item]), $decision)->assertStatus(409);
        $target->forceFill(['two_factor_secret' => encrypt('test-secret'), 'two_factor_recovery_codes' => encrypt(json_encode(['test-code'], JSON_THROW_ON_ERROR)), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($reviewer)->patch(route('security-governance.access-review-items.decide', [$item]), $decision)->assertRedirect();
        $this->assertSame('retain', $item->refresh()->decision);
        $this->assertSame('completed', $campaign->refresh()->status);
        $this->assertSame(64, strlen((string) $campaign->evidence_checksum));
        $this->assertSame(
            "Tathmini ya ufikiaji {$campaign->reference} imeanzishwa kwa vitambulisho 1.",
            AuditEvent::query()->where('subject_id', $campaign->id)->where('action', 'security.access-review.launched')->sole()->description,
        );
    }

    public function test_access_campaign_launch_authorization_and_strong_authentication_guards_follow_the_active_locale(): void
    {
        app()->setLocale('fr');
        $strongLauncher = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $strongReviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        User::factory()->countyAdmin()->withTwoFactor()->create();

        $this->assertCampaignLaunchRejected(
            $strongLauncher,
            [...$this->campaignPayload($strongLauncher, ['county-admin']), 'reference' => 'ACR-GUARD-SELF'],
            'security.access_review.campaign.errors.independent_reviewer_required',
        );

        $unauthorizedReviewer = User::factory()->countyOfficial()->withTwoFactor()->create();
        $this->assertCampaignLaunchRejected(
            $strongLauncher,
            [...$this->campaignPayload($unauthorizedReviewer, ['county-admin']), 'reference' => 'ACR-GUARD-PERMISSION'],
            'security.access_review.campaign.errors.reviewer_not_authorized',
        );

        $weakLauncher = User::factory()->devolutionAdmin()->create();
        $this->assertCampaignLaunchRejected(
            $weakLauncher,
            [...$this->campaignPayload($strongReviewer, ['county-admin']), 'reference' => 'ACR-GUARD-LAUNCHER-MFA'],
            'security.access_review.campaign.errors.launcher_strong_authentication_required',
        );

        $weakReviewer = User::factory()->platformAdmin()->create();
        $this->assertCampaignLaunchRejected(
            $strongLauncher,
            [...$this->campaignPayload($weakReviewer, ['county-admin']), 'reference' => 'ACR-GUARD-REVIEWER-MFA'],
            'security.access_review.campaign.errors.reviewer_strong_authentication_required',
        );

        $this->assertCampaignLaunchRejected(
            $strongLauncher,
            [...$this->campaignPayload($strongReviewer, ['development-partner']), 'reference' => 'ACR-GUARD-EMPTY'],
            'security.access_review.campaign.errors.no_matching_identities',
        );
    }

    public function test_revocation_invalidates_sessions_and_role_scope_then_independent_reinstatement_restores_snapshot(): void
    {
        $county = County::factory()->create([
            'code' => 1,
            'name' => 'Mombasa',
            'logo_path' => '/images/counties/mombasa.webp',
            'logo_source_authority' => 'The National Treasury – Bajeti Yetu',
            'logo_verified_at' => '2026-08-10',
        ]);
        $launcher = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $reviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        $target = User::factory()->countyAdmin($county)->withTwoFactor()->create();
        $target->assignedCounties()->attach($county);
        DB::table((string) config('session.table'))->insert([
            ['id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Security test', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Security test second session', 'payload' => '', 'last_activity' => now()->subMinute()->timestamp],
        ]);
        $this->actingAs($launcher)->post(route('security-governance.access-reviews.store'), $this->campaignPayload($reviewer, ['county-admin']))->assertRedirect();
        $item = AccessReviewItem::query()->sole();
        $this->assertSame('county', $item->assigned_county_snapshot[0]['kind']);
        $this->assertSame('/images/counties/mombasa.webp', $item->assigned_county_snapshot[0]['logoUrl']);
        $this->assertSame('The National Treasury – Bajeti Yetu', $item->assigned_county_snapshot[0]['logoSourceAuthority']);
        $this->actingAs($reviewer)->patch(route('security-governance.access-review-items.decide', [$item]), ['decision' => 'revoke', 'rationale' => 'The access owner confirmed that the county administration assignment has ended and access is no longer required.'])->assertRedirect();
        $this->assertNotNull($target->refresh()->access_revoked_at);
        $this->assertSame(0, $target->roles()->count());
        $this->assertSame(0, $target->assignedCounties()->count());
        $this->assertSame(2, $item->refresh()->sessions_revoked);
        $this->assertSame(0, DB::table((string) config('session.table'))->where('user_id', $target->id)->count());
        $this->actingAs($target)->get(route('dashboard'))->assertRedirect(route('login'));

        $reinstatement = ['rationale' => 'The county access owner supplied a renewed assignment and the security remediation review confirmed strong authentication remains active.', 'approval_reference' => 'ACCESS-REINSTATE-2026-001'];
        $this->actingAs($reviewer)->patch(route('security-governance.access-review-items.reinstate', [$item]), $reinstatement)->assertForbidden();
        $this->actingAs($launcher)->patch(route('security-governance.access-review-items.reinstate', [$item]), $reinstatement)->assertRedirect();
        $this->assertNull($target->refresh()->access_revoked_at);
        $this->assertSame('county-admin', $target->programmeRole()->value);
        $this->assertTrue($target->assignedCounties()->whereKey($county)->exists());
        $this->assertNotNull($item->refresh()->reinstated_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $item->id, 'action' => 'security.access-review.reinstated']);

        $viewer = User::factory()->topManagement()->create();
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($viewer)->get(route('workspace.export', ['security-governance', $format]))->assertOk()->assertDownload();
        }
    }

    public function test_access_review_safeguards_follow_the_active_locale(): void
    {
        $reviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        $campaign = AccessReviewCampaign::factory()->create([
            'reviewer_id' => $reviewer->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $item = AccessReviewItem::factory()->create([
            'access_review_campaign_id' => $campaign->id,
        ]);
        app()->setLocale('fr');

        try {
            app(DecideAccessReviewItem::class)->handle($item, $reviewer, [
                'decision' => 'retain',
                'rationale' => 'La campagne clôturée doit refuser toute nouvelle décision.',
            ]);
            $this->fail('A completed access-review campaign must reject new decisions.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('Cette campagne est clôturée.', $exception->getMessage());
        }
    }

    public function test_access_reinstatement_guards_and_evidence_follow_the_active_locale(): void
    {
        $actor = User::factory()->devolutionAdmin()->withTwoFactor()->create();
        $reviewer = User::factory()->platformAdmin()->withTwoFactor()->create();
        app()->setLocale('fr');

        $assertReinstatementRejected = function (AccessReviewItem $item, string $translationKey) use ($actor): void {
            try {
                app(ReinstateUserAccess::class)->handle($item, $actor, [
                    'rationale' => 'La remédiation indépendante a été vérifiée et l’accès peut être rétabli en toute sécurité.',
                    'approval_reference' => 'ACCÈS-RÉTABLIR-2026-001',
                ]);
                $this->fail("The reinstatement guard {$translationKey} must reject the operation.");
            } catch (HttpException $exception) {
                $this->assertSame((string) __($translationKey), $exception->getMessage());
            }
        };

        $pendingIdentity = User::factory()->platformAdmin()->withTwoFactor()->create(['access_revoked_at' => now()]);
        $assertReinstatementRejected(AccessReviewItem::factory()->for($pendingIdentity, 'user')->create([
            'reviewed_by' => $reviewer->id,
            'decision' => 'pending',
        ]), 'security.access_review.reinstatement.errors.only_revoked');

        $independenceIdentity = User::factory()->platformAdmin()->withTwoFactor()->create(['access_revoked_at' => now()]);
        $assertReinstatementRejected(AccessReviewItem::factory()->for($independenceIdentity, 'user')->create([
            'reviewed_by' => $actor->id,
            'decision' => 'revoke',
            'revoked_at' => now(),
        ]), 'security.access_review.reinstatement.errors.independent_reinstater');

        $reinstatedIdentity = User::factory()->platformAdmin()->withTwoFactor()->create(['access_revoked_at' => now()]);
        $assertReinstatementRejected(AccessReviewItem::factory()->for($reinstatedIdentity, 'user')->create([
            'reviewed_by' => $reviewer->id,
            'decision' => 'revoke',
            'revoked_at' => now(),
            'reinstated_at' => now(),
        ]), 'security.access_review.reinstatement.errors.already_reinstated');

        $activeIdentity = User::factory()->platformAdmin()->withTwoFactor()->create();
        $assertReinstatementRejected(AccessReviewItem::factory()->for($activeIdentity, 'user')->create([
            'reviewed_by' => $reviewer->id,
            'decision' => 'revoke',
            'revoked_at' => now(),
        ]), 'security.access_review.reinstatement.errors.identity_not_suspended');

        $weakAuthenticationIdentity = User::factory()->platformAdmin()->create(['access_revoked_at' => now()]);
        $assertReinstatementRejected(AccessReviewItem::factory()->for($weakAuthenticationIdentity, 'user')->create([
            'reviewed_by' => $reviewer->id,
            'decision' => 'revoke',
            'revoked_at' => now(),
        ]), 'security.access_review.reinstatement.errors.strong_authentication_required');

        $eligibleIdentity = User::factory()->platformAdmin()->withTwoFactor()->create(['access_revoked_at' => now()]);
        $eligibleItem = AccessReviewItem::factory()->for($eligibleIdentity, 'user')->create([
            'reviewed_by' => $reviewer->id,
            'decision' => 'revoke',
            'revoked_at' => now(),
        ]);
        $result = app(ReinstateUserAccess::class)->handle($eligibleItem, $actor, [
            'rationale' => 'La remédiation indépendante a été vérifiée et l’accès peut être rétabli en toute sécurité.',
            'approval_reference' => 'ACCÈS-RÉTABLIR-2026-002',
        ]);

        $this->assertSame(
            'La remédiation indépendante a été vérifiée et l’accès peut être rétabli en toute sécurité. Approbation : ACCÈS-RÉTABLIR-2026-002',
            $result->reinstatement_rationale,
        );
        $this->assertSame(
            'L’accès a été rétabli indépendamment pour l’identité du rôle platform-admin.',
            AuditEvent::query()->where('subject_id', $eligibleItem->id)->where('action', 'security.access-review.reinstated')->sole()->description,
        );
    }

    /** @return array<string, mixed> */
    private function threatPayload(User $owner): array
    {
        return ['owner_id' => $owner->id, 'reference' => 'THR-IDMIS-001', 'title' => 'Compromised privileged identity exports restricted records', 'stride_category' => 'information_disclosure', 'asset' => 'IDMIS restricted records and integration credentials', 'scenario' => 'An attacker obtains a privileged session and exports restricted citizen, employee or assessment information beyond an approved purpose.', 'threat_actor' => 'External attacker using compromised administrator credentials', 'entry_points' => 'login, privileged_exports, integration_credentials', 'likelihood' => 4, 'impact' => 5, 'existing_controls' => 'login_throttling, mfa, passkeys, rbac, immutable_audit', 'treatment_plan' => 'Enforce quarterly access certification, immediate session revocation, SOC export alerts and managed credential rotation.', 'review_due_at' => now()->addMonths(6)->toDateString(), 'evidence_references' => 'AUTH-TESTS, AUDIT-TESTS'];
    }

    /** @param list<string> $roles
     * @return array{reviewer_id: string, reference: string, name: string, scope: string, role_scope: list<string>, period_from: string, period_to: string, due_at: string}
     */
    private function campaignPayload(User $reviewer, array $roles): array
    {
        return ['reviewer_id' => $reviewer->id, 'reference' => 'ACR-2026-Q3-001', 'name' => 'Q3 privileged access certification', 'scope' => 'Review business need, county scope, permission set and strong-authentication posture for all selected programme roles.', 'role_scope' => $roles, 'period_from' => now()->subQuarter()->startOfQuarter()->toDateString(), 'period_to' => now()->subQuarter()->endOfQuarter()->toDateString(), 'due_at' => now()->addDays(21)->toIso8601String()];
    }

    /** @param array{decision: string, treatment_status: string, residual_likelihood: int, residual_impact: int, risk_acceptance_reference?: string|null, review_note: string, evidence_references?: string|null} $attributes */
    private function assertThreatReviewRejected(SecurityThreat $threat, User $actor, array $attributes, string $translationKey): void
    {
        try {
            app(ReviewSecurityThreat::class)->handle($threat, $actor, $attributes);
            $this->fail("The threat review guard {$translationKey} must reject the decision.");
        } catch (HttpException $exception) {
            $this->assertSame((string) __($translationKey), $exception->getMessage());
        }
    }

    /** @param array{reviewer_id: string, reference: string, name: string, scope: string, role_scope: list<string>, period_from: string, period_to: string, due_at: string} $attributes */
    private function assertCampaignLaunchRejected(User $launcher, array $attributes, string $translationKey): void
    {
        try {
            app(LaunchAccessReviewCampaign::class)->handle($launcher, $attributes);
            $this->fail("The campaign launch guard {$translationKey} must reject the operation.");
        } catch (HttpException $exception) {
            $this->assertSame((string) __($translationKey), $exception->getMessage());
        }
    }
}
