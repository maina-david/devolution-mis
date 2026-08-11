<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class IntegrationExchangeIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_client_credentials_token_can_ingest_with_the_narrow_scope(): void
    {
        $client = $this->client('End-to-end source client');
        $plainSecret = $client->plainSecret;
        $system = IntegrationSystem::factory()->create(['oauth_client_id' => $client->id, 'status' => 'active', 'direction' => 'inbound']);
        $contract = $this->contract($system);

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $plainSecret,
            'scope' => 'integrations:ingest',
        ])->assertOk();

        $this->assertLessThanOrEqual(900, $tokenResponse->json('expires_in'));
        $this->assertGreaterThan(0, $tokenResponse->json('expires_in'));

        $this->withToken($tokenResponse->json('access_token'))
            ->postJson(route('api.integration-exchanges.store', $contract), [
                'external_reference' => 'REAL-OAUTH-SOURCE-001',
                'payload' => ['reference' => 'REAL-OAUTH-SOURCE-001', 'amount' => '125.00'],
                'source_occurred_at' => now()->subMinute()->toIso8601String(),
            ], [
                'Idempotency-Key' => 'real-oauth-source-'.Str::uuid(),
                'X-Correlation-ID' => (string) Str::uuid(),
                'X-Source-System' => 'REAL-OAUTH-SOURCE',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('integration_exchanges', ['integration_contract_id' => $contract->id, 'oauth_client_id' => $client->id, 'direction' => 'inbound']);
    }

    public function test_client_credentials_ingestion_is_scoped_validated_encrypted_audited_and_idempotent(): void
    {
        $county = County::factory()->create();
        $client = $this->client('Partner source client');
        $otherClient = $this->client('Unrelated source client');
        $system = IntegrationSystem::factory()->create([
            'oauth_client_id' => $client->id,
            'status' => 'active',
            'direction' => 'inbound',
        ]);
        $contract = $this->contract($system);
        $url = route('api.integration-exchanges.store', $contract);
        $payload = [
            'county_id' => $county->id,
            'external_reference' => 'PARTNER-SOURCE-001',
            'payload' => ['reference' => 'PARTNER-SOURCE-001', 'amount' => '4500000.00'],
            'source_occurred_at' => now()->subMinute()->toIso8601String(),
        ];
        $headers = ['Idempotency-Key' => 'partner-source-'.Str::uuid(), 'X-Correlation-ID' => (string) Str::uuid(), 'X-Source-System' => 'PARTNER-SBX'];

        $this->postJson($url, $payload, $headers)->assertUnauthorized();

        Passport::actingAsClient($client, []);
        $this->postJson($url, $payload, $headers)->assertForbidden();

        Passport::actingAsClient($otherClient, ['integrations:ingest']);
        $this->postJson($url, $payload, $headers)->assertForbidden();

        Passport::actingAsClient($client, ['integrations:ingest']);
        $this->postJson($url, $payload, Arr::except($headers, ['X-Source-System']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('headers');

        $accepted = $this->postJson($url, $payload, $headers)
            ->assertStatus(202)
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.status', 'succeeded');

        $exchange = IntegrationExchange::query()->where('integration_contract_id', $contract->id)->sole();
        $this->assertSame($accepted->json('data.id'), $exchange->id);
        $this->assertSame($client->id, $exchange->oauth_client_id);
        $this->assertSame('inbound', $exchange->direction);
        $this->assertSame($payload['payload'], $exchange->request_payload);
        $this->assertSame(64, mb_strlen($exchange->payload_checksum));
        $requestHeaders = $exchange->request_headers;
        $this->assertIsArray($requestHeaders);
        $this->assertIsArray($requestHeaders['received']);
        $this->assertNotContains('authorization', $requestHeaders['received']);
        $rawPayload = (string) IntegrationExchange::query()->toBase()->where('id', $exchange->id)->value('request_payload');
        $this->assertStringNotContainsString('4500000.00', $rawPayload);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $exchange->id, 'actor_id' => null, 'action' => 'integration.exchange.ingested']);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 1, 'trigger_source' => 'inbound_api', 'outcome' => 'accepted', 'http_status' => 202]);

        $this->postJson($url, $payload, $headers)
            ->assertStatus(202)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $exchange->id)
            ->assertJsonPath('data.replayed', true);
        $this->assertSame(1, IntegrationExchange::query()->where('integration_contract_id', $contract->id)->count());
        $this->assertSame(1, AuditEvent::query()->where('subject_id', $exchange->id)->where('action', 'integration.exchange.ingested')->count());
        $this->assertSame(2, $exchange->refresh()->attempt_count);
        $this->assertDatabaseHas('integration_exchange_attempts', ['integration_exchange_id' => $exchange->id, 'attempt_number' => 2, 'trigger_source' => 'inbound_api', 'outcome' => 'replayed']);

        $this->postJson($url, [...$payload, 'payload' => ['reference' => 'PARTNER-SOURCE-001', 'amount' => '4600000.00']], $headers)
            ->assertConflict();
        $this->assertSame(1, IntegrationExchange::query()->where('integration_contract_id', $contract->id)->count());
    }

    public function test_production_ingestion_stays_closed_until_system_and_contract_approvals_are_present(): void
    {
        $client = $this->client('Production source client');
        $system = IntegrationSystem::factory()->create([
            'oauth_client_id' => $client->id,
            'environment' => 'production',
            'status' => 'active',
            'direction' => 'inbound',
        ]);
        $contract = $this->contract($system);
        $headers = ['Idempotency-Key' => 'production-source-'.Str::uuid(), 'X-Correlation-ID' => (string) Str::uuid(), 'X-Source-System' => 'PRODUCTION-SOURCE'];
        $payload = ['external_reference' => 'PRODUCTION-SOURCE-001', 'payload' => ['reference' => 'PRODUCTION-SOURCE-001', 'amount' => '1.00'], 'source_occurred_at' => now()->subMinute()->toIso8601String()];
        Passport::actingAsClient($client, ['integrations:ingest']);

        $this->postJson(route('api.integration-exchanges.store', $contract), $payload, $headers)->assertConflict();
        $system->update(['production_approval_reference' => 'SOURCE-ACTIVATION-001', 'production_approved_at' => now()]);
        $this->postJson(route('api.integration-exchanges.store', $contract), $payload, $headers)->assertConflict();
        $contract->update(['source_owner_approval_reference' => 'SOURCE-CONTRACT-001', 'data_sharing_agreement_reference' => 'DSA-001']);
        $this->postJson(route('api.integration-exchanges.store', $contract), $payload, $headers)->assertStatus(202);

        $this->assertSame(1, IntegrationExchange::query()->where('integration_contract_id', $contract->id)->count());
    }

    private function client(string $name): Client
    {
        return app(ClientRepository::class)->createClientCredentialsGrantClient($name);
    }

    private function contract(IntegrationSystem $system): IntegrationContract
    {
        return IntegrationContract::factory()->create([
            'integration_system_id' => $system->id,
            'status' => 'published',
            'effective_from' => now()->subMinute(),
            'required_headers' => ['Idempotency-Key', 'X-Correlation-ID', 'X-Source-System'],
            'request_schema' => [
                'type' => 'object',
                'required' => ['reference', 'amount'],
                'properties' => ['reference' => ['type' => 'string'], 'amount' => ['type' => 'string']],
            ],
            'rate_limit_per_minute' => 60,
        ]);
    }
}
