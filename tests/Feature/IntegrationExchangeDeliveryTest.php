<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationExchangeAttempt;
use App\Models\IntegrationSystem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IntegrationExchangeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transient_delivery_is_retried_by_an_explicit_service_identity_with_immutable_attempt_evidence(): void
    {
        $actor = User::factory()->devolutionAdmin()->create();
        $serviceIdentity = User::factory()->platformAdmin()->create(['email' => 'integration-retry@idmis.test']);
        [$system, $contract] = $this->productionContract();
        $this->configureTransport($serviceIdentity);
        Http::preventStrayRequests();
        Http::fakeSequence('https://delivery.example.go.ke/*')
            ->push(['error' => 'temporarily unavailable'], 503)
            ->push(['accepted' => true, 'remote_reference' => 'REMOTE-001'], 202);

        $this->actingAs($actor)->post(route('integrations.exchanges.dispatch', [$contract]), $this->payload('DELIVERY-RETRY-001'))->assertRedirect();
        $exchange = IntegrationExchange::query()->where('idempotency_key', 'DELIVERY-RETRY-001')->sole();
        $this->assertSame('retry_scheduled', $exchange->status);
        $this->assertSame(1, $exchange->attempt_count);
        $this->assertNotNull($exchange->next_attempt_at);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 1, 'trigger_source' => 'initial_dispatch', 'outcome' => 'retry_scheduled', 'http_status' => 503, 'retryable' => true, 'retry_after_seconds' => 1]);

        config()->set('integrations.retry_service_user_email', null);
        $this->assertSame(0, Artisan::call('integrations:retry-exchanges'));
        $this->assertSame(1, $exchange->refresh()->attempt_count);
        config()->set('integrations.retry_service_user_email', User::factory()->countyAdmin()->create()->email);
        $this->assertSame(1, Artisan::call('integrations:retry-exchanges'));
        $this->assertSame(1, $exchange->refresh()->attempt_count);

        config()->set('integrations.retry_service_user_email', $serviceIdentity->email);
        $this->travel(2)->seconds();
        $this->assertSame('retry_scheduled', $exchange->refresh()->status);
        $this->assertSame(0, Artisan::call('integrations:retry-exchanges'));
        $exchange->refresh();
        $this->assertSame('succeeded', $exchange->status);
        $this->assertSame(2, $exchange->attempt_count);
        $this->assertNull($exchange->next_attempt_at);
        $this->assertSame(202, $exchange->http_status);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 2, 'initiated_by' => $serviceIdentity->id, 'trigger_source' => 'scheduled_retry', 'outcome' => 'succeeded', 'http_status' => 202, 'retryable' => false]);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $exchange->id, 'actor_id' => $serviceIdentity->id, 'action' => 'integration.exchange.delivery_attempted']);
        Http::assertSentCount(2);

        $attempt = IntegrationExchangeAttempt::query()->where('integration_exchange_id', $exchange->id)->where('attempt_number', 2)->sole();
        $this->expectException(QueryException::class);
        $attempt->update(['outcome' => 'dead_lettered']);
    }

    public function test_non_retryable_failure_is_dead_lettered_and_manual_retry_is_county_scoped(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $actor = User::factory()->devolutionAdmin()->create();
        $outsideManager = User::factory()->countyAdmin($otherCounty)->create();
        $outsideManager->givePermissionTo([
            ProgrammePermission::ViewIntegrations->value,
            ProgrammePermission::ManageIntegrations->value,
        ]);
        [$system, $contract] = $this->productionContract();
        $this->configureTransport($actor);
        Http::preventStrayRequests();
        Http::fakeSequence('https://delivery.example.go.ke/*')
            ->push(['error' => 'invalid source record'], 400)
            ->push(['accepted' => true], 202);

        $this->actingAs($actor)->post(route('integrations.exchanges.dispatch', [$contract]), [...$this->payload('DELIVERY-DEAD-001'), 'county_id' => $county->id])->assertRedirect();
        $exchange = IntegrationExchange::query()->where('idempotency_key', 'DELIVERY-DEAD-001')->sole();
        $this->assertSame('dead_lettered', $exchange->status);
        $this->assertNull($exchange->next_attempt_at);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 1, 'outcome' => 'dead_lettered', 'http_status' => 400, 'retryable' => false]);

        $this->actingAs($outsideManager)->post(route('integrations.exchanges.retry', [$exchange]))->assertForbidden();
        $this->assertSame(1, $exchange->refresh()->attempt_count);
        $this->actingAs($actor)->post(route('integrations.exchanges.retry', [$exchange]))->assertRedirect();
        $this->assertSame('succeeded', $exchange->refresh()->status);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 2, 'trigger_source' => 'manual_retry', 'outcome' => 'succeeded']);
        Http::assertSentCount(2);

        config()->set('inertia.ssr.enabled', false);
        $this->actingAs($actor)->get(route('integrations.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exchanges.data.0.id', $exchange->id)
            ->where('exchanges.data.0.attemptHistory.0.outcome', 'dead_lettered')
            ->where('exchanges.data.0.attemptHistory.1.trigger', 'manual_retry')
            ->where('exchanges.data.0.attemptHistory.1.outcome', 'succeeded'));
        foreach (['csv', 'json'] as $format) {
            $content = $this->actingAs($actor)->get(route('workspace.export', ['integrations', $format]))->assertOk()->streamedContent();
            $this->assertStringContainsString('Latest attempt outcome', $content);
            $this->assertStringContainsString('succeeded', $content);
        }
        $this->actingAs($actor)->get(route('workspace.export', ['integrations', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($actor)->get(route('workspace.export', ['integrations', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $outsideExport = $this->actingAs($outsideManager)->get(route('workspace.export', ['integrations', 'json']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('DELIVERY-DEAD-001', $outsideExport);
    }

    /** @return array{IntegrationSystem, IntegrationContract} */
    private function productionContract(): array
    {
        $system = IntegrationSystem::factory()->create(['code' => fake()->unique()->bothify('DELIVERY-###'), 'environment' => 'production', 'transport' => 'https_json', 'auth_scheme' => 'bearer_vault', 'credential_reference' => 'delivery_test', 'base_url' => 'https://delivery.example.go.ke', 'direction' => 'outbound', 'status' => 'active', 'production_approval_reference' => 'SOURCE-ACTIVATION-001', 'production_approved_at' => now()->subDay()]);
        $contract = IntegrationContract::factory()->create(['integration_system_id' => $system->id, 'status' => 'published', 'effective_from' => now()->subDay(), 'source_owner_approval_reference' => 'SOURCE-CONTRACT-001', 'data_sharing_agreement_reference' => 'DSA-001', 'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => [1, 2]], 'request_schema' => ['type' => 'object', 'required' => ['reference'], 'properties' => ['reference' => ['type' => 'string']]]]);

        return [$system, $contract];
    }

    private function configureTransport(User $serviceIdentity): void
    {
        config()->set('integrations.allowed_hosts', ['delivery.example.go.ke']);
        config()->set('integrations.credentials.delivery_test', 'vault-test-token');
        config()->set('integrations.retry_service_user_email', $serviceIdentity->email);
    }

    /** @return array<string, mixed> */
    private function payload(string $idempotencyKey): array
    {
        return ['idempotency_key' => $idempotencyKey, 'external_reference' => $idempotencyKey, 'payload' => ['reference' => $idempotencyKey], 'source_occurred_at' => now()->subMinute()->toIso8601String()];
    }
}
