<?php

namespace App\Actions;

use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationExchangeAttempt;
use App\Services\AuditLogger;
use App\Services\IntegrationPayloadValidator;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngestIntegrationExchange
{
    public function __construct(private IntegrationPayloadValidator $validator, private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string|null>  $headers
     * @return array{exchange: IntegrationExchange, replayed: bool}
     */
    public function handle(IntegrationContract $contract, string $clientId, array $attributes, array $headers): array
    {
        $startedAt = now();
        $startedNs = hrtime(true);
        $contract->load('system.oauthClient');
        $system = $contract->system;

        abort_unless($contract->status === 'published' && ($contract->effective_from === null || $contract->effective_from->isPast()) && ($contract->effective_to === null || $contract->effective_to->isFuture()), 409, __('integrations.exchange.errors.effective_inbound_contract'));
        abort_unless(in_array($system->direction, ['inbound', 'bidirectional'], true), 409, __('integrations.exchange.errors.inbound_not_approved'));
        abort_unless($system->status === 'active', 409, __('integrations.exchange.errors.system_inactive'));
        abort_unless($system->oauth_client_id === $clientId, 403, __('integrations.exchange.errors.oauth_client_unbound'));

        if ($system->environment === 'production') {
            abort_unless($system->production_approved_at !== null && filled($system->production_approval_reference), 409, __('integrations.exchange.errors.production_source_approval'));
            abort_unless(filled($contract->source_owner_approval_reference) && filled($contract->data_sharing_agreement_reference), 409, __('integrations.exchange.errors.production_agreements'));
        }

        $missingHeaders = collect($contract->required_headers ?? [])
            ->filter(fn (string $header): bool => blank(Arr::get($headers, mb_strtolower($header))))
            ->values();
        if ($missingHeaders->isNotEmpty()) {
            throw ValidationException::withMessages(['headers' => __('integrations.exchange.errors.missing_headers', ['headers' => $missingHeaders->implode(', ')])]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $attributes['payload'];
        $this->validator->validate($payload, $contract->request_schema);
        $canonicalPayload = json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $payloadChecksum = hash('sha256', $canonicalPayload);
        $idempotencyKey = (string) $attributes['idempotency_key'];

        return DB::transaction(function () use ($contract, $system, $clientId, $attributes, $headers, $payload, $payloadChecksum, $idempotencyKey, $startedAt, $startedNs): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$idempotencyKey]);

            $existing = IntegrationExchange::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->integration_contract_id === $contract->id && $existing->oauth_client_id === $clientId && hash_equals($existing->payload_checksum, $payloadChecksum), 409, __('integrations.exchange.errors.idempotency_conflict'));
                $attemptNumber = $existing->attempt_count + 1;
                $existing->update(['attempt_count' => $attemptNumber]);
                $this->recordInboundAttempt($existing, $attemptNumber, 'replayed', $system->oauthClient?->name, $startedAt, $startedNs);

                return ['exchange' => $existing, 'replayed' => true];
            }

            $exchange = IntegrationExchange::create([
                'integration_contract_id' => $contract->id,
                'county_id' => $attributes['county_id'] ?? null,
                'oauth_client_id' => $clientId,
                'direction' => 'inbound',
                'correlation_id' => $attributes['correlation_id'],
                'external_reference' => $attributes['external_reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_payload' => $payload,
                'response_payload' => ['accepted' => true],
                'request_headers' => [
                    'received' => collect(array_keys($headers))->filter(fn (string $name): bool => $name !== 'authorization')->values()->all(),
                    'source_client_id' => $clientId,
                ],
                'payload_checksum' => $payloadChecksum,
                'status' => 'succeeded',
                'http_status' => 202,
                'attempt_count' => 1,
                'source_occurred_at' => $attributes['source_occurred_at'],
                'accepted_at' => now(),
                'processed_at' => now(),
                'completed_at' => now(),
            ]);
            $this->recordInboundAttempt($exchange, 1, 'accepted', $system->oauthClient?->name, $startedAt, $startedNs);

            $this->auditLogger->record(null, $exchange, 'integration.exchange.ingested', __('integrations.exchange.audit.ingested', ['correlation' => $exchange->correlation_id, 'system' => $system->code]), $exchange->county_id, ['contract_id' => $contract->id, 'oauth_client_id' => $clientId, 'payload_checksum' => $payloadChecksum]);

            return ['exchange' => $exchange, 'replayed' => false];
        });
    }

    private function recordInboundAttempt(IntegrationExchange $exchange, int $attemptNumber, string $outcome, ?string $clientName, CarbonInterface $startedAt, int $startedNs): void
    {
        $completedAt = now();
        IntegrationExchangeAttempt::create([
            'integration_exchange_id' => $exchange->id,
            'initiated_by_name' => $clientName ?? __('integrations.exchange.oauth_client'),
            'attempt_number' => $attemptNumber,
            'trigger_source' => 'inbound_api',
            'outcome' => $outcome,
            'http_status' => 202,
            'retryable' => false,
            'response_checksum' => hash('sha256', json_encode(['accepted' => true], JSON_THROW_ON_ERROR)),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'duration_ms' => max(0, (int) round((hrtime(true) - $startedNs) / 1_000_000)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalize(array $payload): array
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value) && ! array_is_list($value)) {
                $value = $this->canonicalize($value);
            }
        }

        return $payload;
    }
}
