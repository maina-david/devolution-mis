<?php

namespace App\Http\Controllers;

use App\Services\OperationalReadinessCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(OperationalReadinessCheck $check): JsonResponse
    {
        $result = $check->run();

        return response()->json(['status' => $result['ready'] ? 'ready' : 'not_ready', 'checkedAt' => $result['checked_at'], 'checks' => collect($result['checks'])->map(fn (array $value, string $name): array => ['name' => $name, 'status' => $value['status'], 'latencyMs' => $value['latency_ms']])->values()], $result['ready'] ? 200 : 503);
    }
}
