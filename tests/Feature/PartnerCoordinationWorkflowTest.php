<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\DocumentLink;
use App\Models\Organization;
use App\Models\PartnerAgreement;
use App\Models\PartnerAgreementChangeDecision;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\PartnerCollaborationActionUpdateDecision;
use App\Models\PartnerCollaborationAlert;
use App\Models\PartnerCollaborationPlan;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionReconciliation;
use App\Models\PartnerOperationalAlert;
use App\Models\PartnerProfile;
use App\Models\Permission;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Notifications\ProgrammeAlert;
use App\Services\PartnerOverlapAnalyzer;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartnerCoordinationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_registers_a_uuid_partner_profile_with_scoped_portfolios(): void
    {
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $organization = Organization::factory()->create(['type' => 'development_partner']);
        $representative = User::factory()->developmentPartner()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $release = $this->publishedReferenceRelease([$county], [$sector], [$organization], $administrator);

        $this->actingAs($administrator)->post(route('partners.profiles.store'), [
            'organization_id' => $organization->id,
            'partner_type' => 'multilateral',
            'country' => 'Kenya',
            'website' => 'https://partner.example.org',
            'focal_point_name' => 'Programme Lead',
            'focal_point_email' => 'programme.lead@partner.example.org',
            'strategic_priorities' => 'Climate-resilient county services.',
            'modalities' => ['grant', 'technical_assistance'],
            'county_ids' => [$county->id],
            'sector_ids' => [$sector->id],
            'user_ids' => [$representative->id],
        ])->assertRedirect();

        $partner = PartnerProfile::query()->sole();
        $this->assertTrue(Str::isUuid($partner->id));
        $this->assertTrue($partner->counties()->whereKey($county)->exists());
        $this->assertTrue($partner->sectors()->whereKey($sector)->exists());
        $this->assertTrue($partner->users()->whereKey($representative)->exists());
        $this->assertSame($release->id, $partner->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $partner->id, 'action' => 'partner.profile.created']);
        $event = AuditEvent::query()->where('subject_id', $partner->id)->where('action', 'partner.profile.created')->sole();
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);

        $this->actingAs($administrator)->get(route('partners.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.columns.4', 'Reference release')
                ->where('workspace.rows.0.cells.4', "v{$release->version} · {$release->effective_from?->toDateString()}")
                ->where('workspace.rows.0.cells.5', $release->checksum));
        foreach (['json', 'csv'] as $format) {
            $export = $this->actingAs($administrator)->get(route('workspace.export', ['partners', $format]))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString('Reference release', $export);
            $this->assertStringContainsString("v{$release->version}", $export);
            $this->assertStringContainsString($release->checksum, $export);
        }
        $this->actingAs($administrator)->get(route('workspace.export', ['partners', 'xlsx']))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($administrator)->get(route('workspace.export', ['partners', 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $partner->delete();
        $this->assertSoftDeleted($partner);
    }

    public function test_partner_profile_creation_fails_closed_without_a_complete_effective_reference_release(): void
    {
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $organization = Organization::factory()->create(['type' => 'development_partner']);
        $administrator = User::factory()->devolutionAdmin()->create();
        $payload = $this->partnerProfilePayload($organization, $county, $sector);

        $this->actingAs($administrator)->post(route('partners.profiles.store'), $payload)
            ->assertStatus(409);
        $this->assertDatabaseCount('partner_profiles', 0);

        $this->publishedReferenceRelease([$county], [$sector], [], $administrator);
        $this->actingAs($administrator)->post(route('partners.profiles.store'), $payload)
            ->assertSessionHasErrors('organization_id');
        $this->assertDatabaseCount('partner_profiles', 0);

        $release = $this->publishedReferenceRelease([$county], [$sector], [$organization], $administrator);
        $this->actingAs($administrator)->post(route('partners.profiles.store'), $payload)
            ->assertRedirect();

        $this->assertSame($release->id, PartnerProfile::query()->sole()->reference_data_release_id);
    }

    public function test_county_partner_manager_cannot_register_a_profile_for_an_outside_county(): void
    {
        $home = County::factory()->create();
        $outside = County::factory()->create();
        $sector = Sector::factory()->create();
        $organization = Organization::factory()->create(['type' => 'development_partner']);
        $countyAdministrator = User::factory()->countyAdmin($home)->create();
        $countyAdministrator->givePermissionTo(Permission::findOrCreate(ProgrammePermission::ManagePartners->value, 'web'));
        $this->publishedReferenceRelease([$home, $outside], [$sector], [$organization], $countyAdministrator);

        $this->actingAs($countyAdministrator)->post(
            route('partners.profiles.store'),
            $this->partnerProfilePayload($organization, $outside, $sector),
        )->assertForbidden();

        $this->assertDatabaseCount('partner_profiles', 0);
    }

    public function test_county_page_and_exports_include_only_partners_covering_that_county(): void
    {
        $home = County::factory()->create(['name' => 'Visible County']);
        $other = County::factory()->create(['name' => 'Hidden County']);
        $countyUser = User::factory()->countyAdmin($home)->create();
        $visible = PartnerProfile::factory()->create();
        $visible->counties()->attach($home);
        $hidden = PartnerProfile::factory()->create();
        $hidden->counties()->attach($other);

        $this->actingAs($countyUser)->get(route('partners.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('partners/index')
                ->where('workspace.pagination.total', 1)
                ->where('workspace.rows.0.id', $visible->id)
                ->where('portfolioMap.showFullCountry', false)
                ->has('portfolioMap.counties', 1)
                ->where('portfolioMap.counties.0.id', $home->id));

        $export = $this->actingAs($countyUser)->get(route('workspace.export', ['partners', 'json']))->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString($visible->organization->name, $content);
        $this->assertStringNotContainsString($hidden->organization->name, $content);
    }

    public function test_partner_representative_can_report_only_for_own_profile_and_authorized_project_scope(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $representative = User::factory()->developmentPartner()->create();
        $representative->assignedCounties()->attach($county);
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $partner->sectors()->attach(Sector::factory()->create());
        $partner->users()->attach($representative, ['relationship_role' => 'authorized_representative']);
        $otherPartner = PartnerProfile::factory()->create();
        $otherPartner->counties()->attach($county);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $project->counties()->attach($county, ['is_lead' => true]);
        $hiddenProject = DevolutionProject::factory()->create(['lead_county_id' => $otherCounty->id]);
        $hiddenProject->counties()->attach($otherCounty, ['is_lead' => true]);

        $this->actingAs($representative)->post(route('partners.contributions.store'), $this->contributionPayload($partner, $project))->assertRedirect();
        $contribution = PartnerContribution::query()->sole();
        $this->assertSame($representative->id, $contribution->reported_by);
        $this->assertSame($representative->id, $contribution->provenance['captured_by']);

        $this->actingAs($representative)->post(route('partners.contributions.store'), $this->contributionPayload($otherPartner, $project, 'loan'))->assertForbidden();
        $this->actingAs($representative)->post(route('partners.contributions.store'), $this->contributionPayload($partner, $hiddenProject, 'in_kind'))->assertForbidden();
        $this->actingAs($representative)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('portfolioMap.showFullCountry', true)
            ->has('portfolioMap.counties', 1)
            ->where('portfolioMap.counties.0.id', $county->id)
            ->where('portfolioMap.counties.0.partnerCount', 2));
    }

    public function test_overlap_analysis_is_idempotent_and_authorized_resolver_records_a_decision(): void
    {
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $first = PartnerProfile::factory()->create();
        $second = PartnerProfile::factory()->create();
        foreach ([$first, $second] as $partner) {
            $partner->counties()->attach($county);
            $partner->sectors()->attach($sector);
        }

        $analyzer = app(PartnerOverlapAnalyzer::class);
        $this->assertCount(1, $analyzer->analyze());
        $this->assertCount(1, $analyzer->analyze());
        $this->assertSame(1, PartnerCollaborationAlert::query()->count());
        $alert = PartnerCollaborationAlert::query()->sole();
        $this->assertSame('synergy', $alert->alert_type);

        $countyUser = User::factory()->countyAdmin($county)->create();
        $this->actingAs($countyUser)->patch(route('partners.alerts.resolve', [$alert]), ['status' => 'resolved', 'resolution' => 'Joint planning session scheduled.'])->assertForbidden();

        $administrator = User::factory()->devolutionAdmin()->create();
        $this->actingAs($administrator)->patch(route('partners.alerts.resolve', [$alert]), ['status' => 'resolved', 'resolution' => 'Joint planning session scheduled.'])->assertRedirect();
        $this->assertSame('resolved', $alert->refresh()->status);
        $this->assertSame($administrator->id, $alert->resolved_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $alert->id, 'action' => 'partner.alert.resolved']);
    }

    public function test_agreement_requires_repository_record_and_independent_approval_with_scoped_access(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $representative = User::factory()->developmentPartner()->create();
        $representative->assignedCounties()->attach($county);
        $approver = User::factory()->topManagement()->create();
        $approver->assignedCounties()->attach($county);
        $outsider = User::factory()->countyAdmin($outsideCounty)->create();
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $partner->users()->attach($representative, ['relationship_role' => 'authorized_representative']);
        $this->partnerAgreementWorkflow();

        $this->actingAs($administrator)->post(route('partners.agreements.store'), [
            'partner_profile_id' => $partner->id,
            'reference' => 'MOU-GOV-001',
            'title' => 'County climate services cooperation agreement',
            'agreement_type' => 'mou',
            'starts_on' => '2026-09-01',
            'ends_on' => '2029-08-31',
            'committed_value' => 120000000,
            'currency' => 'KES',
            'summary' => 'A governed multi-year cooperation agreement.',
        ])->assertRedirect();

        $agreement = PartnerAgreement::query()->sole();
        $this->assertTrue(Str::isUuid($agreement->id));
        $this->assertSame('draft', $agreement->status);
        $this->assertSame('draft', $agreement->workflow?->current_state);

        $this->actingAs($administrator)->patch(route('partners.agreements.transition', [$agreement]), [
            'transition' => 'submit',
        ])->assertSessionHasErrors('transition');

        $this->actingAs($representative)->post(route('partners.agreements.documents.store', [$agreement]), [
            'title' => 'Signed cooperation agreement',
            'category' => 'Agreement',
            'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('signed-agreement.jpg'),
        ])->assertRedirect();

        $link = DocumentLink::query()->with('document.currentVersion')->sole();
        $this->assertSame($agreement->id, $link->subject_id);
        $this->assertSame('partner-agreement-record', $link->purpose);
        $this->assertSame($county->id, $link->document->county_id);
        $this->assertNotNull($link->document->currentVersion);
        Storage::disk('local')->assertExists($link->document->path);

        $this->actingAs($outsider)->get(route('evidence.preview', [$link->document]))->assertForbidden();
        $this->actingAs($approver)->get(route('evidence.preview', [$link->document]))->assertOk();

        $this->actingAs($administrator)->patch(route('partners.agreements.transition', [$agreement]), [
            'transition' => 'submit',
            'comment' => 'Signed record and commercial terms checked.',
        ])->assertRedirect();
        $this->assertSame('pending_approval', $agreement->refresh()->status);

        $administrator->givePermissionTo(ProgrammePermission::ApprovePartnerAgreements->value);
        $this->actingAs($administrator)->patch(route('partners.agreements.transition', [$agreement]), [
            'transition' => 'approve',
        ])->assertForbidden();

        $this->actingAs($approver)->patch(route('partners.agreements.transition', [$agreement]), [
            'transition' => 'approve',
            'comment' => 'Independent review completed.',
        ])->assertRedirect();
        $this->assertSame('active', $agreement->refresh()->status);
        $this->assertSame($approver->id, $agreement->approved_by);
        $this->assertNotNull($agreement->approved_at);

        $this->actingAs($representative)->post(route('partners.agreements.documents.store', [$agreement]), [
            'title' => 'Late annex',
            'category' => 'Annex',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late-annex.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);

        $this->actingAs($approver)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('capabilities.approveAgreements', true)
            ->where('agreements.0.id', $agreement->id)
            ->where('agreements.0.status', 'active')
            ->has('agreements.0.documents', 1));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $agreement->id, 'action' => 'partner.agreement.approve']);
    }

    public function test_contribution_reconciliation_requires_clean_evidence_and_an_independent_reviewer(): void
    {
        Storage::fake('local');
        $county = County::factory()->create(['logo_path' => '/counties/test.svg']);
        $representative = User::factory()->developmentPartner()->create();
        $representative->assignedCounties()->attach($county);
        $reviewer = User::factory()->devolutionAdmin()->create();
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $partner->users()->attach($representative, ['relationship_role' => 'authorized_representative']);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $project->counties()->attach($county, ['is_lead' => true]);

        $this->actingAs($representative)->post(route('partners.contributions.store'), $this->contributionPayload($partner, $project))->assertRedirect();
        $contribution = PartnerContribution::query()->sole();
        $decision = ['decision' => 'verified', 'verified_committed_amount' => '10000000.00', 'verified_disbursed_amount' => '2500000.00', 'verified_in_kind_value' => '0.00', 'source_reference' => 'BANK-STATEMENT-001', 'review_note' => 'Bank statement and partner ledger totals independently agree.'];

        $this->actingAs($reviewer)->post(route('partners.contributions.reconciliations.store', [$contribution]), $decision)->assertStatus(422);
        $this->actingAs($representative)->post(route('partners.contributions.documents.store', [$contribution]), [
            'title' => 'Certified disbursement statement', 'category' => 'Financial record', 'source_type' => 'scanned', 'document' => UploadedFile::fake()->image('statement.jpg'),
        ])->assertRedirect();
        $link = DocumentLink::query()->with('document')->sole();
        $link->document->update(['scan_status' => 'clean', 'record_status' => 'active']);

        $representative->givePermissionTo(ProgrammePermission::ManagePartners->value);
        $this->actingAs($representative)->post(route('partners.contributions.reconciliations.store', [$contribution]), $decision)->assertForbidden();
        $this->actingAs($reviewer)->post(route('partners.contributions.reconciliations.store', [$contribution]), $decision)->assertRedirect();

        $reconciliation = PartnerContributionReconciliation::query()->sole();
        $this->assertTrue(Str::isUuid($reconciliation->id));
        $this->assertSame(1, $reconciliation->version);
        $this->assertSame($reviewer->id, $reconciliation->reviewed_by);
        $this->assertSame(64, strlen($reconciliation->evidence_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $contribution->id, 'action' => 'partner.contribution.reconciled']);

        $this->actingAs($reviewer)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('contributions.0.id', $contribution->id)
            ->where('contributions.0.county.logoUrl', '/counties/test.svg')
            ->where('contributions.0.reconciliations.0.decision', 'verified')
            ->has('contributions.0.documents', 1));

        $this->expectException(QueryException::class);
        $reconciliation->update(['review_note' => 'Attempted mutation.']);
    }

    public function test_active_agreement_changes_require_clean_evidence_and_independent_immutable_decision(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->topManagement()->create();
        $approver->assignedCounties()->attach($county);
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $agreement = PartnerAgreement::factory()->create(['partner_profile_id' => $partner->id, 'status' => 'active', 'approved_by' => $approver->id, 'approved_at' => now()]);
        $payload = ['change_type' => 'suspension', 'reason' => 'Material delivery controls require a temporary implementation pause.', 'effective_on' => now()->addDay()->toDateString()];

        $this->actingAs($requester)->post(route('partners.agreement-changes.store', [$agreement]), $payload)->assertRedirect();
        $change = PartnerAgreementChangeRequest::query()->sole();
        $this->assertTrue(Str::isUuid($change->id));
        $this->assertSame(1, $change->version);
        $this->assertSame($requester->id, $change->requested_by);

        $decision = ['decision' => 'approved', 'decision_note' => 'Independent review confirms that suspension is proportionate and evidenced.'];
        $this->actingAs($approver)->post(route('partners.agreement-changes.decision.store', [$change]), $decision)->assertStatus(422);
        $this->actingAs($requester)->post(route('partners.agreement-changes.documents.store', [$change]), [
            'title' => 'Signed suspension instrument', 'category' => 'Agreement change', 'source_type' => 'digital', 'document' => UploadedFile::fake()->create('suspension.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $document = DocumentLink::query()->where('subject_id', $change->id)->with('document')->sole()->document;
        $document->update(['scan_status' => 'clean', 'record_status' => 'active']);

        $requester->givePermissionTo(ProgrammePermission::ApprovePartnerAgreements->value);
        $this->actingAs($requester)->post(route('partners.agreement-changes.decision.store', [$change]), $decision)->assertForbidden();
        $this->actingAs($approver)->post(route('partners.agreement-changes.decision.store', [$change]), $decision)->assertRedirect();

        $retained = PartnerAgreementChangeDecision::query()->sole();
        $this->assertSame('suspended', $agreement->refresh()->status);
        $this->assertSame($approver->id, $retained->decided_by);
        $this->assertSame(64, strlen($retained->decision_checksum));
        $this->actingAs($requester)->post(route('partners.agreement-changes.documents.store', [$change]), [
            'title' => 'Late record', 'category' => 'Agreement change', 'source_type' => 'digital', 'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $agreement->id, 'action' => 'partner.agreement.change_decided']);
        $this->actingAs($approver)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('agreements.0.id', $agreement->id)
            ->where('agreements.0.status', 'suspended')
            ->where('agreements.0.changeRequests.0.decision.result', 'approved')
            ->has('agreements.0.changeRequests.0.documents', 1));

        $this->expectException(QueryException::class);
        $retained->update(['decision_note' => 'Attempted mutation.']);
    }

    public function test_scheduled_partner_monitor_is_idempotent_scoped_and_resolvable(): void
    {
        Notification::fake();
        config()->set('partners.agreement_expiry_notice_days', 60);
        config()->set('partners.contribution_reconciliation_due_days', 30);
        $county = County::factory()->create(['logo_path' => '/counties/monitor.svg']);
        $outsideCounty = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $representative = User::factory()->developmentPartner()->create();
        $representative->assignedCounties()->attach($county);
        $outsider = User::factory()->countyAdmin($outsideCounty)->create();
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $partner->users()->attach($representative, ['relationship_role' => 'authorized_representative']);
        PartnerAgreement::factory()->create(['partner_profile_id' => $partner->id, 'status' => 'active', 'ends_on' => today()->addDays(15)]);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $project->counties()->attach($county, ['is_lead' => true]);
        PartnerContribution::factory()->create(['partner_profile_id' => $partner->id, 'devolution_project_id' => $project->id, 'reported_by' => $representative->id, 'created_at' => now()->subDays(45)]);
        $exceptionContribution = PartnerContribution::factory()->create(['partner_profile_id' => $partner->id, 'devolution_project_id' => $project->id, 'reported_by' => $representative->id, 'contribution_type' => 'loan']);
        $exception = PartnerContributionReconciliation::factory()->create(['partner_contribution_id' => $exceptionContribution->id, 'reviewed_by' => $manager->id, 'decision' => 'exception']);

        $this->artisan('partners:monitor-operational-alerts')->assertSuccessful();
        $this->assertSame(3, PartnerOperationalAlert::query()->count());
        Notification::assertSentToTimes($representative, ProgrammeAlert::class, 3);
        Notification::assertSentToTimes($manager, ProgrammeAlert::class, 3);

        $this->artisan('partners:monitor-operational-alerts')->assertSuccessful();
        $this->assertSame(3, PartnerOperationalAlert::query()->count());
        Notification::assertSentToTimes($representative, ProgrammeAlert::class, 3);

        PartnerContributionReconciliation::factory()->create(['partner_contribution_id' => $exceptionContribution->id, 'reviewed_by' => $manager->id, 'version' => 2, 'decision' => 'verified', 'predecessor_checksum' => $exception->decision_checksum]);
        $this->artisan('partners:monitor-operational-alerts')->assertSuccessful();
        $this->assertSame('system_resolved', PartnerOperationalAlert::query()->where('alert_type', 'contribution_reconciliation_exception')->value('status'));

        $this->actingAs($outsider)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('operationalAlerts', 0));
        $alert = PartnerOperationalAlert::query()->where('alert_type', 'agreement_expiry_due')->sole();
        $this->actingAs($manager)->patch(route('partners.operational-alerts.resolve', [$alert]), ['status' => 'resolved', 'resolution' => 'Renewal review completed and a controlled follow-up action was assigned.'])->assertRedirect();
        $this->assertSame('resolved', $alert->refresh()->status);
        $this->assertSame($manager->id, $alert->resolved_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $alert->id, 'action' => 'partner.operational_alert.resolved']);

        $this->actingAs($manager)->withSession(['locale' => 'sw'])->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('operationalAlerts', 3)
            ->where('operationalAlerts.0.county.logoUrl', '/counties/monitor.svg')
            ->where('localization.current', 'sw')
            ->where('localization.partnerCoordination.operational_control_alerts', 'Tahadhari za udhibiti wa uendeshaji')
            ->where('localization.partnerCoordination.status_accepted_risk', 'Hatari imekubaliwa'));
        $export = $this->actingAs($manager)->get(route('workspace.export', ['partners', 'json']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Open alerts', $export);
        $this->assertStringContainsString($partner->organization->name, $export);
    }

    public function test_collaboration_plan_actions_require_independent_evidence_backed_progress_verification(): void
    {
        Storage::fake('local');
        $county = County::factory()->create(['logo_path' => '/counties/action.svg']);
        $manager = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->topManagement()->create();
        $approver->assignedCounties()->attach($county);
        $owner = User::factory()->developmentPartner()->create();
        $owner->assignedCounties()->attach($county);
        $accountableOrganization = Organization::factory()->create(['name' => 'County Delivery Secretariat']);
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $partner->users()->attach($owner, ['relationship_role' => 'authorized_representative']);
        $release = $this->publishedReferenceRelease([$county], [], [$accountableOrganization], $manager);

        $this->actingAs($manager)->post(route('partners.collaboration-plans.store'), ['partner_profile_id' => $partner->id, 'reference' => 'COLLAB-2026-001', 'title' => 'County service delivery collaboration', 'objective' => 'Coordinate accountable partner and county delivery commitments.', 'starts_on' => today()->addDay()->toDateString(), 'ends_on' => today()->addMonths(6)->toDateString()])->assertRedirect();
        $plan = PartnerCollaborationPlan::query()->sole();
        $this->actingAs($manager)->patch(route('partners.collaboration-plans.transition', [$plan]), ['transition' => 'submit'])->assertRedirect();
        $manager->givePermissionTo(ProgrammePermission::ApprovePartnerAgreements->value);
        $this->actingAs($manager)->patch(route('partners.collaboration-plans.transition', [$plan]), ['transition' => 'approve', 'decision_note' => 'Self approval attempt must fail.'])->assertForbidden();
        $this->actingAs($approver)->patch(route('partners.collaboration-plans.transition', [$plan]), ['transition' => 'approve', 'decision_note' => 'Independent review confirms the plan is measurable and within scope.'])->assertRedirect();

        $this->actingAs($manager)->post(route('partners.collaboration-actions.store', [$plan]), ['county_id' => $county->id, 'code' => 'ACT-001', 'title' => 'Deploy county reporting toolkit', 'description' => 'Deploy and validate the approved reporting toolkit with county officers.', 'accountable_user_id' => $owner->id, 'accountable_organization_id' => $accountableOrganization->id, 'due_on' => today()->addMonths(3)->toDateString()])->assertRedirect();
        $action = PartnerCollaborationAction::query()->sole();
        $this->assertSame($release->id, $action->reference_data_release_id);
        $creationEvent = AuditEvent::query()->where('subject_id', $action->id)->where('action', 'partner.collaboration_action.created')->sole();
        $this->assertSame($release->version, $creationEvent->metadata['reference_data_release_version']);
        $this->assertSame($release->checksum, $creationEvent->metadata['reference_data_release_checksum']);
        $updatePayload = ['progress_percentage' => 100, 'narrative' => 'Toolkit deployment and county validation have been completed successfully.'];
        $this->actingAs($owner)->post(route('partners.collaboration-action-updates.store', [$action]), $updatePayload)->assertStatus(422);
        $this->actingAs($owner)->post(route('partners.collaboration-actions.documents.store', [$action]), ['title' => 'Signed deployment acceptance', 'category' => 'Action evidence', 'source_type' => 'scanned', 'document' => UploadedFile::fake()->image('acceptance.jpg')])->assertRedirect();
        $link = DocumentLink::query()->where('subject_id', $action->id)->with('document')->sole();
        $link->document->update(['scan_status' => 'clean', 'record_status' => 'active']);
        $this->actingAs($owner)->post(route('partners.collaboration-action-updates.store', [$action]), $updatePayload)->assertRedirect();
        $update = PartnerCollaborationActionUpdate::query()->sole();

        $owner->givePermissionTo(ProgrammePermission::ApprovePartnerAgreements->value);
        $decision = ['decision' => 'verified', 'verification_note' => 'Independent inspection confirms the submitted completion evidence.'];
        $this->actingAs($owner)->post(route('partners.collaboration-action-updates.decision.store', [$update]), $decision)->assertForbidden();
        $this->actingAs($approver)->post(route('partners.collaboration-action-updates.decision.store', [$update]), $decision)->assertRedirect();
        $retained = PartnerCollaborationActionUpdateDecision::query()->sole();
        $this->assertSame('completed', $action->refresh()->status);
        $this->assertSame('100.00', $action->progress_percentage);
        $this->assertSame(64, strlen($retained->decision_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $action->id, 'action' => 'partner.collaboration_action.update_decided']);

        $this->actingAs($approver)->get(route('partners.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('collaborationPlans.0.id', $plan->id)
            ->where('collaborationPlans.0.actions.0.county.logoUrl', '/counties/action.svg')
            ->where('collaborationPlans.0.actions.0.ownerOrganization', 'County Delivery Secretariat')
            ->where('collaborationPlans.0.actions.0.referenceData.version', $release->version)
            ->where('collaborationPlans.0.actions.0.referenceData.checksum', $release->checksum)
            ->where('collaborationPlans.0.actions.0.status', 'completed')
            ->where('collaborationPlans.0.actions.0.updates.0.decision.result', 'verified'));

        $export = $this->actingAs($manager)->get(route('workspace.export', ['partner-actions', 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Owner organization', $export);
        $this->assertStringContainsString('County Delivery Secretariat', $export);
        $this->assertStringContainsString($release->checksum, $export);

        $this->expectException(QueryException::class);
        $retained->update(['verification_note' => 'Attempted mutation.']);
    }

    public function test_collaboration_action_progress_evidence_is_database_immutable(): void
    {
        $update = PartnerCollaborationActionUpdate::factory()->create();

        $this->expectException(QueryException::class);
        $update->update(['narrative' => 'Attempted mutation.']);
    }

    public function test_collaboration_action_requires_a_checksum_valid_catalogue_and_independent_county_scope(): void
    {
        $county = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $countyManager = User::factory()->countyAdmin($county)->create();
        $countyManager->givePermissionTo(ProgrammePermission::ManagePartners->value);
        $owner = User::factory()->developmentPartner()->create();
        $owner->assignedCounties()->attach([$county->id, $outsideCounty->id]);
        $organization = Organization::factory()->create();
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach([$county->id, $outsideCounty->id]);
        $plan = PartnerCollaborationPlan::factory()->create(['partner_profile_id' => $partner->id, 'status' => 'active', 'created_by' => $manager->id, 'starts_on' => today()->subDay(), 'ends_on' => today()->addMonth()]);
        $payload = ['county_id' => $county->id, 'code' => 'ACT-CATALOGUE-001', 'title' => 'Validate governed catalogue boundary', 'description' => 'Prove that the action retains its governed county and organization lineage.', 'accountable_user_id' => $owner->id, 'accountable_organization_id' => $organization->id, 'due_on' => today()->addWeek()->toDateString()];

        $this->actingAs($manager)->post(route('partners.collaboration-actions.store', [$plan]), $payload)->assertStatus(409);

        $snapshot = ['counties' => [['id' => $county->id]], 'organizations' => [['id' => $organization->id]], 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];
        ReferenceDataRelease::factory()->create(['version' => 1, 'approved_by' => $manager->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => str_repeat('a', 64), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
        $this->actingAs($manager)->post(route('partners.collaboration-actions.store', [$plan]), $payload)->assertStatus(409);

        $this->publishedReferenceRelease([$county], [], [$organization], $manager);
        $this->actingAs($countyManager)->post(route('partners.collaboration-actions.store', [$plan]), [...$payload, 'county_id' => $outsideCounty->id])->assertForbidden();
        $this->assertDatabaseCount('partner_collaboration_actions', 0);
    }

    public function test_collaboration_action_deadlines_are_idempotent_scoped_and_exportable_in_four_formats(): void
    {
        Notification::fake();
        config()->set('partners.collaboration_action_reminder_days', 7);
        $county = County::factory()->create(['name' => 'Action Export County']);
        $outside = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $owner = User::factory()->developmentPartner()->create();
        $owner->assignedCounties()->attach($county);
        $outsider = User::factory()->countyAdmin($outside)->create();
        $partner = PartnerProfile::factory()->create();
        $partner->counties()->attach($county);
        $plan = PartnerCollaborationPlan::factory()->create(['partner_profile_id' => $partner->id, 'status' => 'active', 'created_by' => $manager->id]);
        $upcoming = PartnerCollaborationAction::factory()->create(['partner_collaboration_plan_id' => $plan->id, 'county_id' => $county->id, 'code' => 'EXPORT-ACT-001', 'accountable_user_id' => $owner->id, 'created_by' => $manager->id, 'due_on' => today()->addDays(3)]);
        $overdue = PartnerCollaborationAction::factory()->create(['partner_collaboration_plan_id' => $plan->id, 'county_id' => $county->id, 'code' => 'EXPORT-ACT-002', 'accountable_user_id' => $owner->id, 'created_by' => $manager->id, 'due_on' => today()->subDay()]);

        $this->artisan('partners:send-action-reminders')->assertSuccessful();
        $this->assertNotNull($upcoming->refresh()->reminder_sent_at);
        $this->assertNull($upcoming->escalated_at);
        $this->assertNotNull($overdue->refresh()->reminder_sent_at);
        $this->assertNotNull($overdue->escalated_at);
        Notification::assertSentToTimes($owner, ProgrammeAlert::class, 2);
        Notification::assertSentToTimes($manager, ProgrammeAlert::class, 1);

        $this->artisan('partners:send-action-reminders')->assertSuccessful();
        Notification::assertSentToTimes($owner, ProgrammeAlert::class, 2);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $overdue->id, 'action' => 'partner.collaboration_action.escalated']);

        foreach (['csv', 'json'] as $format) {
            $content = $this->actingAs($manager)->get(route('workspace.export', ['partner-actions', $format]))->assertOk()->streamedContent();
            $this->assertStringContainsString('EXPORT-ACT-001', $content);
            $this->assertStringContainsString('Action Export County', $content);
        }
        $currentActions = $this->actingAs($manager)->get(route('workspace.export', ['partner-actions',
            'json',
            'county_id' => $county->id,
            'status' => 'open',
            'from' => today()->toDateString(),
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('EXPORT-ACT-001', $currentActions);
        $this->assertStringNotContainsString('EXPORT-ACT-002', $currentActions);

        $completedActions = $this->actingAs($manager)->get(route('workspace.export', ['partner-actions',
            'json',
            'status' => 'completed',
        ]))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('EXPORT-ACT-001', $completedActions);
        $this->assertStringNotContainsString('EXPORT-ACT-002', $completedActions);

        $this->actingAs($manager)->get(route('workspace.export', ['partner-actions', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($manager)->get(route('workspace.export', ['partner-actions', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

        $outsideExport = $this->actingAs($outsider)->get(route('workspace.export', ['partner-actions', 'json']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('EXPORT-ACT-001', $outsideExport);
    }

    /** @return array<string, mixed> */
    private function partnerProfilePayload(Organization $organization, County $county, Sector $sector): array
    {
        return [
            'organization_id' => $organization->id,
            'partner_type' => 'multilateral',
            'country' => 'Kenya',
            'website' => 'https://partner.example.org',
            'focal_point_name' => 'Programme Lead',
            'focal_point_email' => 'programme.lead@partner.example.org',
            'strategic_priorities' => 'Climate-resilient county services.',
            'modalities' => ['grant', 'technical_assistance'],
            'county_ids' => [$county->id],
            'sector_ids' => [$sector->id],
            'user_ids' => [],
        ];
    }

    /**
     * @param  list<County>  $counties
     * @param  list<Sector>  $sectors
     * @param  list<Organization>  $organizations
     */
    private function publishedReferenceRelease(array $counties, array $sectors, array $organizations, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => collect($organizations)->map(fn (Organization $organization): array => ['id' => $organization->id])->all(),
            'sectors' => collect($sectors)->map(fn (Sector $sector): array => ['id' => $sector->id])->all(),
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
            'approval_reference' => 'SDD-MDM-PARTNER-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function contributionPayload(PartnerProfile $partner, DevolutionProject $project, string $type = 'grant'): array
    {
        return [
            'partner_profile_id' => $partner->id,
            'devolution_project_id' => $project->id,
            'financial_year' => '2026/2027',
            'contribution_type' => $type,
            'committed_amount' => 10000000,
            'disbursed_amount' => 2500000,
            'in_kind_value' => 0,
            'currency' => 'KES',
            'description' => 'Programme contribution.',
            'status' => 'disbursing',
            'provenance' => ['source_system' => 'partner-portal', 'captured_at' => now()->toIso8601String()],
        ];
    }

    private function partnerAgreementWorkflow(): WorkflowDefinition
    {
        $definition = WorkflowDefinition::factory()->create(['code' => 'PARTNER-AGREEMENT-LIFECYCLE', 'module' => 'partner-coordination']);
        WorkflowVersion::factory()->published()->create(['workflow_definition_id' => $definition->id, 'configuration' => [
            'initial_state' => 'draft', 'states' => ['draft', 'pending_approval', 'active', 'rejected'], 'terminal_states' => ['active', 'rejected'], 'start_permission' => ProgrammePermission::ManagePartners->value,
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'pending_approval', 'permission' => ProgrammePermission::ManagePartners->value, 'rules' => [['field' => 'document_count', 'operator' => 'gte', 'value' => 1]]],
                ['name' => 'approve', 'from' => 'pending_approval', 'to' => 'active', 'permission' => ProgrammePermission::ApprovePartnerAgreements->value, 'separation_from' => ['submit'], 'terminal' => true],
                ['name' => 'reject', 'from' => 'pending_approval', 'to' => 'rejected', 'permission' => ProgrammePermission::ApprovePartnerAgreements->value, 'separation_from' => ['submit'], 'terminal' => true],
            ], 'rules' => [],
        ]]);

        return $definition;
    }
}
