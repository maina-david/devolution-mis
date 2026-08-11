<?php

namespace App\Services;

use App\Models\IntegrationContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntegrationTransportManager
{
    /** @param array<string, mixed> $payload
     * @return array{status:int,body:array<string,mixed>}
     */
    public function send(IntegrationContract $contract, array $payload, string $correlationId): array
    {
        $system = $contract->system;
        if ($system->transport === 'fixture') {
            abort_unless($system->environment === 'sandbox', 409, 'Fixture transport is restricted to sandbox systems.');

            return ['status' => 202, 'body' => ['accepted' => true, 'correlation_id' => $correlationId, 'transport' => 'fixture']];
        }

        if ($system->transport !== 'https_json') {
            throw new RuntimeException("Unsupported integration transport [{$system->transport}].");
        }
        abort_unless($system->status === 'active' && $system->production_approved_at && $system->production_approval_reference, 409, 'Production exchange requires recorded source-owner activation approval.');
        abort_unless($system->base_url && $system->credential_reference, 409, 'Production endpoint and credential-vault reference are required.');
        $allowedHosts = config('integrations.allowed_hosts', []);
        $host = parse_url($system->base_url, PHP_URL_HOST);
        abort_unless(is_string($host) && in_array($host, $allowedHosts, true), 409, 'Integration endpoint host is not allowlisted.');
        $token = config("integrations.credentials.{$system->credential_reference}");
        abort_unless(is_string($token) && $token !== '', 409, 'Credential-vault reference is unresolved.');

        $response = Http::baseUrl($system->base_url)->withToken($token)->acceptJson()->withHeaders(['X-Correlation-ID' => $correlationId])->connectTimeout(5)->timeout(20)->send($contract->http_method, $contract->path, ['json' => $payload]);

        return ['status' => $response->status(), 'body' => is_array($response->json()) ? $response->json() : ['response' => $response->body()]];
    }
}
