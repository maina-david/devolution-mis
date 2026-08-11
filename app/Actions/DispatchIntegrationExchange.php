<?php

namespace App\Actions;

use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\User;
use App\Services\IntegrationPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchIntegrationExchange
{
    public function __construct(private IntegrationPayloadValidator $validator, private AttemptIntegrationExchangeDelivery $delivery) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IntegrationContract $contract, User $actor, array $attributes): IntegrationExchange
    {
        $contract->load('system');
        abort_unless($contract->status === 'published' && ($contract->effective_from === null || $contract->effective_from->isPast()) && ($contract->effective_to === null || $contract->effective_to->isFuture()), 409, 'Only an effective published interface contract can exchange data.');
        $payload = $attributes['payload'];
        $this->validator->validate($payload, $contract->request_schema);
        $canonical = json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $idempotencyKey = (string) $attributes['idempotency_key'];
        $correlationId = (string) Str::uuid();
        $payloadChecksum = hash('sha256', $canonical);
        $exchange = DB::transaction(function () use ($contract, $actor, $attributes, $payload, $idempotencyKey, $correlationId, $payloadChecksum): IntegrationExchange {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$idempotencyKey]);
            $existing = IntegrationExchange::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($existing->integration_contract_id === $contract->id && hash_equals($existing->payload_checksum, $payloadChecksum), 409, 'The idempotency key is already associated with a different exchange.');

                return $existing;
            }

            return IntegrationExchange::create(['integration_contract_id' => $contract->id, 'county_id' => $attributes['county_id'] ?? null, 'created_by' => $actor->id, 'direction' => 'outbound', 'correlation_id' => $correlationId, 'external_reference' => $attributes['external_reference'] ?? null, 'idempotency_key' => $idempotencyKey, 'request_payload' => $payload, 'request_headers' => ['X-Correlation-ID' => $correlationId, 'Idempotency-Key' => $idempotencyKey], 'payload_checksum' => $payloadChecksum, 'status' => 'accepted', 'attempt_count' => 0, 'source_occurred_at' => $attributes['source_occurred_at'] ?? null, 'accepted_at' => now()]);
        });
        if ($exchange->attempt_count > 0) {
            return $exchange;
        }

        return $this->delivery->handle($exchange, $actor, 'initial_dispatch');
    }

    /** @param array<string, mixed> $payload
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
