<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use App\Models\Organization;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionSourceMatch;
use App\Models\ReconciliationException;
use App\Models\ReconciliationRun;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_is_independently_published_and_sandbox_exchange_is_validated_encrypted_idempotent_and_exported(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $organization = Organization::factory()->create(['status' => 'active']);
        $release = $this->publishedReferenceRelease($author, [$organization]);
        $this->actingAs($author)->post(route('integrations.systems.store'), $this->systemPayload(['owner_organization_id' => $organization->id]))->assertRedirect();
        $system = IntegrationSystem::query()->sole();
        $this->assertSame($release->id, $system->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $system->id, 'action' => 'integration.system.created']);
        $this->actingAs($author)->get(route('integrations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('catalogue.available', true)
            ->where('systems.0.referenceData.version', $release->version)
            ->where('systems.0.referenceData.checksum', $release->checksum));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($author)->get(route('workspace.export', ['integration-systems', $format]))->assertOk()->assertDownload();
        }
        $systemCsv = $this->actingAs($author)->get(route('workspace.export', ['integration-systems', 'csv']))->streamedContent();
        $this->assertStringContainsString('Reference release', $systemCsv);
        $this->assertStringContainsString($release->checksum, $systemCsv);
        $this->actingAs($author)->post(route('integrations.contracts.store'), $this->contractPayload($system))->assertRedirect();
        $contract = IntegrationContract::query()->sole();
        $this->assertTrue(Str::isUuid($contract->id));
        $this->assertSame(64, strlen($contract->content_checksum));
        $this->actingAs($author)->patch(route('integrations.contracts.publish', [$contract]), ['effective_from' => now()->subMinute()->toIso8601String()])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('integrations.contracts.publish', [$contract]), ['effective_from' => now()->subMinute()->toIso8601String()])->assertRedirect();
        $this->assertSame('published', $contract->refresh()->status);

        $payload = ['employee_reference' => 'IPPD-000123', 'full_name' => 'Protected Person', 'employment_status' => 'active'];
        $request = ['idempotency_key' => 'IPPD-EMPLOYEE-000123-v1', 'external_reference' => 'IPPD-000123', 'payload' => $payload, 'source_occurred_at' => now()->subHour()->toIso8601String()];
        $this->actingAs($author)->post(route('integrations.exchanges.dispatch', [$contract]), $request)->assertRedirect();
        $exchange = IntegrationExchange::query()->sole();
        $this->assertSame('succeeded', $exchange->status);
        $this->assertSame(202, $exchange->http_status);
        $this->assertSame($payload, $exchange->request_payload);
        $this->assertSame(64, strlen($exchange->payload_checksum));
        $rawPayload = (string) IntegrationExchange::query()->toBase()->where('id', $exchange->id)->value('request_payload');
        $this->assertStringNotContainsString('Protected Person', $rawPayload);
        $this->actingAs($author)->post(route('integrations.exchanges.dispatch', [$contract]), $request)->assertRedirect();
        $this->assertDatabaseCount('integration_exchanges', 1);
        $this->actingAs($author)->post(route('integrations.exchanges.dispatch', [$contract]), [...$request, 'idempotency_key' => 'IPPD-INVALID', 'payload' => ['employee_reference' => 'IPPD-000124']])->assertSessionHasErrors('payload.full_name');
        $this->assertDatabaseCount('integration_exchanges', 1);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($author)->get(route('workspace.export', ['integrations', $format]))->assertOk()->assertDownload();
        }
        $this->assertDatabaseHas('audit_events', ['subject_id' => $exchange->id, 'action' => 'integration.exchange.delivery_attempted']);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 1, 'trigger_source' => 'initial_dispatch', 'outcome' => 'succeeded']);
    }

    public function test_production_transport_remains_closed_without_source_owner_activation_and_vault_credential(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $this->publishedReferenceRelease($author);
        $systemData = [...$this->systemPayload(), 'code' => 'IFMIS', 'name' => 'Integrated Financial Management Information System', 'environment' => 'production', 'transport' => 'https_json', 'auth_scheme' => 'bearer_vault', 'base_url' => 'https://ifmis.example.go.ke', 'credential_reference' => 'ifmis', 'status' => 'active'];
        $this->actingAs($author)->post(route('integrations.systems.store'), $systemData)->assertRedirect();
        $system = IntegrationSystem::query()->sole();
        $this->actingAs($author)->post(route('integrations.contracts.store'), $this->contractPayload($system))->assertRedirect();
        $contract = IntegrationContract::query()->sole();
        $this->actingAs($reviewer)->patch(route('integrations.contracts.publish', [$contract]), ['source_owner_approval_reference' => 'TREASURY-CONTRACT-TEST-001', 'data_sharing_agreement_reference' => 'DSA-TEST-001', 'effective_from' => now()->subMinute()->toIso8601String()])->assertRedirect();
        $this->actingAs($author)->post(route('integrations.exchanges.dispatch', [$contract]), ['idempotency_key' => 'IFMIS-CLOSED-GATE-001', 'payload' => ['employee_reference' => 'IPPD-000125', 'full_name' => 'Test User', 'employment_status' => 'active']])->assertRedirect();
        $exchange = IntegrationExchange::query()->sole();
        $this->assertSame('dead_lettered', $exchange->status);
        $this->assertSame('configuration', $exchange->error_category);
        $this->assertStringContainsString('source-owner activation approval', (string) $exchange->error_detail);
        $this->assertNull($system->refresh()->production_approved_at);
        $this->actingAs($author)->patch(route('integrations.systems.activate', [$system]), ['production_approval_reference' => 'TREASURY-ACTIVATION-001', 'production_approved_at' => now()->subMinute()->toIso8601String()])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('integrations.systems.activate', [$system]), ['production_approval_reference' => 'TREASURY-ACTIVATION-001', 'production_approved_at' => now()->subMinute()->toIso8601String()])->assertRedirect();
        $this->assertSame('TREASURY-ACTIVATION-001', $system->refresh()->production_approval_reference);
        config(['integrations.allowed_hosts' => ['ifmis.example.go.ke'], 'integrations.credentials.ifmis' => 'vault-test-token']);
        Http::fake(['https://ifmis.example.go.ke/*' => Http::response(['accepted' => true, 'remote_reference' => 'IFMIS-ACK-001'], 202)]);
        $this->actingAs($author)->post(route('integrations.exchanges.dispatch', [$contract]), ['idempotency_key' => 'IFMIS-ACTIVE-001', 'payload' => ['employee_reference' => 'IPPD-000126', 'full_name' => 'Test User Two', 'employment_status' => 'active']])->assertRedirect();
        $this->assertDatabaseHas('integration_exchanges', ['idempotency_key' => 'IFMIS-ACTIVE-001', 'status' => 'succeeded', 'http_status' => 202]);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer vault-test-token') && $request->hasHeader('X-Correlation-ID'));
    }

    public function test_system_registration_fails_closed_for_missing_corrupt_or_incomplete_catalogue_state(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $organization = Organization::factory()->create(['status' => 'active']);
        $payload = $this->systemPayload(['owner_organization_id' => $organization->id]);

        $this->actingAs($author)->post(route('integrations.systems.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('integration_systems', 0);

        $this->publishedReferenceRelease($author, [], str_repeat('0', 64));
        $this->actingAs($author)->post(route('integrations.systems.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('integration_systems', 0);

        $this->publishedReferenceRelease($author);
        try {
            app(EffectiveReferenceDataReleaseResolver::class)->forIntegrationSystem($organization->id, now());
            $this->fail('An owner organization outside the effective snapshot must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner_organization_id', $exception->errors());
        }
        $this->assertDatabaseCount('integration_systems', 0);
    }

    public function test_reconciliation_exception_resolution_is_county_scoped_and_closes_run_with_checksum(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $manager = User::factory()->topManagement()->create();
        $outsider = User::factory()->topManagement()->create();
        $manager->assignedCounties()->attach($county);
        $outsider->assignedCounties()->attach($otherCounty);
        $system = IntegrationSystem::create($this->systemPayload());
        $run = ReconciliationRun::create(['integration_system_id' => $system->id, 'initiated_by' => $manager->id, 'reference' => 'REC-IPPD-2026-001', 'period_from' => today()->startOfMonth(), 'period_to' => today(), 'source_count' => 10, 'target_count' => 9, 'matched_count' => 9, 'exception_count' => 1, 'status' => 'exceptions', 'started_at' => now()->subMinute()]);
        $exception = ReconciliationException::create(['reconciliation_run_id' => $run->id, 'county_id' => $county->id, 'external_reference' => 'IPPD-000999', 'local_reference' => 'IDMIS-USER-999', 'exception_type' => 'missing_target', 'field_name' => 'employee_reference', 'severity' => 'high', 'expected_value' => 'IPPD-000999', 'actual_value' => null, 'description' => 'Authoritative employee record has no corresponding active IDMIS user.', 'status' => 'open']);
        $this->actingAs($outsider)->patch(route('integrations.exceptions.resolve', [$exception]), ['resolution' => 'Out-of-scope attempt.'])->assertForbidden();
        $this->actingAs($manager)->patch(route('integrations.exceptions.resolve', [$exception]), ['resolution' => 'Identity steward verified the departure and disabled the unmatched local access record.'])->assertRedirect();
        $this->assertSame('resolved', $exception->refresh()->status);
        $this->assertSame('reconciled', $run->refresh()->status);
        $this->assertSame(64, strlen((string) $run->result_checksum));
    }

    public function test_partner_contribution_source_matching_is_idempotent_immutable_scoped_and_does_not_bypass_human_reconciliation(): void
    {
        App::setLocale('fr');
        $county = County::factory()->create(['name' => 'Partner Source County']);
        $otherCounty = County::factory()->create(['name' => 'Outside Source County']);
        $sourceOperator = User::factory()->devolutionAdmin()->create();
        $serviceIdentity = User::factory()->platformAdmin()->create(['email' => 'partner-reconciliation@idmis.test']);
        $countyViewer = User::factory()->countyAdmin($county)->create();
        $countyViewer->givePermissionTo(ProgrammePermission::ViewIntegrations->value);
        $outsideViewer = User::factory()->countyAdmin($otherCounty)->create();
        $outsideViewer->givePermissionTo(ProgrammePermission::ViewIntegrations->value);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $matchedContribution = PartnerContribution::factory()->create(['devolution_project_id' => $project->id, 'committed_amount' => 10000000, 'disbursed_amount' => 4000000, 'in_kind_value' => 0, 'currency' => 'KES']);
        $mismatchedContribution = PartnerContribution::factory()->create(['devolution_project_id' => $project->id, 'committed_amount' => 8000000, 'disbursed_amount' => 3000000, 'in_kind_value' => 0, 'currency' => 'KES']);
        $scopeMismatchContribution = PartnerContribution::factory()->create(['devolution_project_id' => $project->id, 'committed_amount' => 6000000, 'disbursed_amount' => 2000000, 'in_kind_value' => 0, 'currency' => 'KES']);
        $system = IntegrationSystem::factory()->create(['code' => 'PARTNER-IFMIS-SBX', 'environment' => 'sandbox', 'direction' => 'inbound']);
        $contract = IntegrationContract::factory()->create([
            'integration_system_id' => $system->id,
            'resource_name' => 'partner_contribution_statement',
            'status' => 'published',
            'approved_by' => $serviceIdentity->id,
            'effective_from' => now()->subDay(),
            'request_schema' => [
                'type' => 'object',
                'required' => ['partner_contribution_id', 'external_reference', 'committed_amount', 'disbursed_amount', 'in_kind_value', 'currency'],
                'properties' => collect(['partner_contribution_id', 'external_reference', 'committed_amount', 'disbursed_amount', 'in_kind_value', 'currency'])->mapWithKeys(fn (string $field): array => [$field => ['type' => 'string']])->all(),
            ],
        ]);

        $this->inboundContributionExchange($contract, $sourceOperator, $county, 'SRC-MATCHED-001', $matchedContribution->id, '10000000.00', '4000000.00');
        $this->inboundContributionExchange($contract, $sourceOperator, $county, 'SRC-VARIANCE-001', $mismatchedContribution->id, '8000000.00', '2500000.00');
        $this->inboundContributionExchange($contract, $sourceOperator, $county, 'SRC-MISSING-001', (string) Str::uuid(), '5000000.00', '1000000.00');
        $this->inboundContributionExchange($contract, $sourceOperator, $otherCounty, 'SRC-SCOPE-001', $scopeMismatchContribution->id, '6000000.00', '2000000.00');

        config()->set('partners.contribution_exchange_lookback_days', 7);
        config()->set('partners.reconciliation_service_user_email', $countyViewer->email);
        $this->assertSame(1, Artisan::call('partners:reconcile-contribution-exchanges'));
        $this->assertStringContainsString('L’identité de service de rapprochement partenaire configurée est absente ou non autorisée.', Artisan::output());
        $this->assertDatabaseCount('reconciliation_runs', 0);

        config()->set('partners.reconciliation_service_user_email', $serviceIdentity->email);
        $this->assertSame(0, Artisan::call('partners:reconcile-contribution-exchanges'));
        $this->assertStringContainsString('Un traitement de rapprochement d’une source de contribution partenaire a été effectué.', Artisan::output());

        $run = ReconciliationRun::query()->sole();
        $this->assertSame(4, $run->source_count);
        $this->assertSame(3, $run->target_count);
        $this->assertSame(1, $run->matched_count);
        $this->assertSame(3, $run->exception_count);
        $this->assertSame('exceptions', $run->status);
        $this->assertSame(64, strlen((string) $run->result_checksum));
        $this->assertDatabaseCount('partner_contribution_source_matches', 4);
        $this->assertTrue(PartnerContributionSourceMatch::query()->get()->every(fn (PartnerContributionSourceMatch $match): bool => Str::isUuid($match->id) && $match->id[14] === '7'));
        $this->assertDatabaseHas('partner_contribution_source_matches', ['partner_contribution_id' => $matchedContribution->id, 'outcome' => 'matched']);
        $this->assertDatabaseHas('partner_contribution_source_matches', ['partner_contribution_id' => $mismatchedContribution->id, 'outcome' => 'value_mismatch', 'disbursement_variance' => '-500000.00']);
        $this->assertDatabaseHas('partner_contribution_source_matches', ['external_reference' => 'SRC-MISSING-001', 'outcome' => 'missing_target']);
        $this->assertDatabaseHas('partner_contribution_source_matches', ['partner_contribution_id' => $scopeMismatchContribution->id, 'outcome' => 'county_scope_mismatch']);
        $this->assertDatabaseCount('reconciliation_exceptions', 3);
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'exception_type' => 'value_mismatch',
            'description' => 'La comparaison de la source de contribution partenaire a produit value_mismatch ; un examen humain et des preuves DMS saines sont requis avant toute décision de rapprochement.',
        ]);
        $this->assertDatabaseCount('partner_contribution_reconciliations', 0);
        $this->assertSame('3000000.00', $mismatchedContribution->refresh()->disbursed_amount);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $run->id,
            'action' => 'partner.contribution.exchange_reconciled',
            'description' => "Le traitement de source de contribution partenaire {$run->reference} s’est terminé avec 3 exceptions.",
        ]);

        $this->assertSame(0, Artisan::call('partners:reconcile-contribution-exchanges'));
        $this->assertDatabaseCount('reconciliation_runs', 1);
        $this->assertDatabaseCount('partner_contribution_source_matches', 4);

        foreach (['csv', 'json'] as $format) {
            $countyExport = $this->actingAs($countyViewer)->get(route('workspace.export', ['integrations', $format]))->assertOk()->streamedContent();
            $this->assertStringContainsString('SRC-MATCHED-001', $countyExport);
            $this->assertStringContainsString('value_mismatch', $countyExport);
        }
        $this->actingAs($countyViewer)->get(route('workspace.export', ['integrations', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($countyViewer)->get(route('workspace.export', ['integrations', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $outsideExport = $this->actingAs($outsideViewer)->get(route('workspace.export', ['integrations', 'json']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('SRC-MATCHED-001', $outsideExport);

        $retainedMatch = PartnerContributionSourceMatch::query()->where('outcome', 'matched')->sole();
        $this->expectException(QueryException::class);
        $retainedMatch->update(['outcome' => 'value_mismatch']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function systemPayload(array $overrides = []): array
    {
        return [...['code' => 'IPPD-SANDBOX', 'name' => 'IPPD sandbox', 'purpose' => 'Test the governed employee-reference exchange and reconciliation contract without production connectivity.', 'system_owner' => 'State Department for Public Service', 'environment' => 'sandbox', 'transport' => 'fixture', 'auth_scheme' => 'none', 'direction' => 'inbound', 'data_classification' => 'confidential', 'status' => 'contract_review'], ...$overrides];
    }

    /** @param list<Organization> $organizations */
    private function publishedReferenceRelease(User $approver, array $organizations = [], ?string $checksum = null): ReferenceDataRelease
    {
        $snapshot = ['counties' => [], 'organizations' => array_map(fn (Organization $organization): array => ['id' => $organization->id], $organizations), 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => $checksum ?? app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-INTEGRATIONS-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function contractPayload(IntegrationSystem $system): array
    {
        return ['integration_system_id' => $system->id, 'name' => 'Employee reference status exchange', 'resource_name' => 'employee_status', 'http_method' => 'POST', 'path' => '/v1/employees/status', 'request_schema' => ['type' => 'object', 'required' => ['employee_reference', 'full_name', 'employment_status'], 'properties' => ['employee_reference' => ['type' => 'string'], 'full_name' => ['type' => 'string'], 'employment_status' => ['type' => 'string']]], 'response_schema' => ['type' => 'object', 'required' => ['accepted']], 'field_mappings' => ['employee_reference' => 'performance_plans.hris_employee_reference'], 'required_headers' => ['X-Correlation-ID', 'Idempotency-Key'], 'idempotency_field' => 'employee_reference', 'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => [60, 300, 1800]], 'rate_limit_per_minute' => 60];
    }

    private function inboundContributionExchange(IntegrationContract $contract, User $sourceOperator, County $county, string $externalReference, string $contributionId, string $committedAmount, string $disbursedAmount): IntegrationExchange
    {
        $payload = ['partner_contribution_id' => $contributionId, 'external_reference' => $externalReference, 'committed_amount' => $committedAmount, 'disbursed_amount' => $disbursedAmount, 'in_kind_value' => '0.00', 'currency' => 'KES'];

        return IntegrationExchange::factory()->create(['integration_contract_id' => $contract->id, 'county_id' => $county->id, 'created_by' => $sourceOperator->id, 'direction' => 'inbound', 'external_reference' => $externalReference, 'idempotency_key' => $externalReference.'-v1', 'request_payload' => $payload, 'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'source_occurred_at' => now()->subHour(), 'accepted_at' => now()->subHour()]);
    }
}
