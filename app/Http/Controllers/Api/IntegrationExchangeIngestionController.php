<?php

namespace App\Http\Controllers\Api;

use App\Actions\IngestIntegrationExchange;
use App\Http\Controllers\Controller;
use App\Http\Requests\IngestIntegrationExchangeRequest;
use App\Models\IntegrationContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Guards\TokenGuard;

class IntegrationExchangeIngestionController extends Controller
{
    public function __invoke(IngestIntegrationExchangeRequest $request, IntegrationContract $contract, IngestIntegrationExchange $action): JsonResponse
    {
        $guard = Auth::guard('api');
        abort_unless($guard instanceof TokenGuard && $guard->client() !== null, 401, __('integrations.valid_oauth_client_required'));

        /** @var array<string, string|null> $headers */
        $headers = collect($request->headers->all())->mapWithKeys(fn (array $values, string $name): array => [mb_strtolower($name) => $values[0] ?? null])->all();
        $result = $action->handle($contract, (string) $guard->client()->getKey(), $request->validated(), $headers);
        $exchange = $result['exchange'];

        return response()->json([
            'data' => [
                'id' => $exchange->id,
                'correlation_id' => $exchange->correlation_id,
                'external_reference' => $exchange->external_reference,
                'payload_checksum' => $exchange->payload_checksum,
                'status' => $exchange->status,
                'accepted_at' => $exchange->accepted_at->toIso8601String(),
                'replayed' => $result['replayed'],
            ],
        ], 202)->header('Idempotent-Replayed', $result['replayed'] ? 'true' : 'false');
    }
}
