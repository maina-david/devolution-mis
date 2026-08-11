<?php

namespace App\Actions;

use App\Models\PerformanceTestRun;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class RunHttpPerformanceProbe
{
    public function handle(string $baseUrl, string $routePath, int $requestCount, int $concurrency, ?User $initiator = null): PerformanceTestRun
    {
        $targetUrl = $this->validatedTarget($baseUrl, $routePath, $requestCount, $concurrency);
        $startedAt = now();
        $startedNs = hrtime(true);
        $result = Process::timeout((int) config('operations.performance.process_timeout_seconds', 120))
            ->run([(string) config('operations.performance.binary', '/usr/sbin/ab'), '-q', '-l', '-n', (string) $requestCount, '-c', (string) $concurrency, $targetUrl]);
        $completedAt = now();
        $durationMs = (int) round((hrtime(true) - $startedNs) / 1_000_000);
        $output = $result->output()."\n".$result->errorOutput();
        $metrics = $this->parse($output);
        $thresholds = [
            'minimum_requests_per_second' => (float) config('operations.performance.minimum_requests_per_second', 10),
            'maximum_p95_latency_ms' => (float) config('operations.performance.maximum_p95_latency_ms', 1000),
            'maximum_failed_requests' => (int) config('operations.performance.maximum_failed_requests', 0),
        ];
        $failedRequests = $metrics['failed_requests'] ?? $requestCount;
        $successfulRequests = max(0, $requestCount - $failedRequests);
        $outcome = $result->successful()
            && $failedRequests <= $thresholds['maximum_failed_requests']
            && ($metrics['requests_per_second'] ?? 0) >= $thresholds['minimum_requests_per_second']
            && ($metrics['p95_latency_ms'] ?? INF) <= $thresholds['maximum_p95_latency_ms'] ? 'pass' : 'fail';
        $evidence = [
            'environment' => app()->environment(),
            'tool' => 'apachebench',
            'target_url' => $targetUrl,
            'route_path' => $routePath,
            'request_count' => $requestCount,
            'concurrency' => $concurrency,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            ...$metrics,
            'duration_ms' => $durationMs,
            'threshold_snapshot' => $thresholds,
            'outcome' => $outcome,
            'initiated_by' => $initiator?->id,
            'initiated_by_name' => $initiator !== null ? $initiator->name : 'system:performance-probe',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'output_checksum' => hash('sha256', $output),
        ];

        return PerformanceTestRun::create([
            ...$evidence,
            'error_category' => $result->successful() ? null : 'probe_process_failed',
            'error_detail' => null,
            'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ]);
    }

    private function validatedTarget(string $baseUrl, string $routePath, int $requestCount, int $concurrency): string
    {
        if ($requestCount < 1 || $requestCount > (int) config('operations.performance.maximum_requests', 10000)) {
            throw new InvalidArgumentException('Request count is outside the configured safe range.');
        }

        if ($concurrency < 1 || $concurrency > min($requestCount, (int) config('operations.performance.maximum_concurrency', 100))) {
            throw new InvalidArgumentException('Concurrency is outside the configured safe range.');
        }

        $allowedPaths = config('operations.performance.allowed_paths', ['/up']);
        if (! is_array($allowedPaths) || ! in_array($routePath, $allowedPaths, true)) {
            throw new InvalidArgumentException('The requested route is not approved for performance probing.');
        }

        $parts = parse_url(rtrim($baseUrl, '/'));
        $allowedHosts = config('operations.performance.allowed_hosts', []);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || ! is_array($allowedHosts) || ! in_array($parts['host'], $allowedHosts, true) || (isset($parts['port']) && $parts['port'] !== 443) || ! in_array($parts['path'] ?? '', ['', '/'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The target must be an approved same-environment HTTPS host.');
        }

        return rtrim($baseUrl, '/').$routePath;
    }

    /** @return array{failed_requests?: int, requests_per_second?: float, mean_latency_ms?: float, p50_latency_ms?: float, p95_latency_ms?: float, p99_latency_ms?: float} */
    private function parse(string $output): array
    {
        $metrics = [];
        $patterns = [
            'failed_requests' => '/Failed requests:\s+(\d+)/i',
            'requests_per_second' => '/Requests per second:\s+([\d.]+)/i',
            'mean_latency_ms' => '/Time per request:\s+([\d.]+) \[ms\] \(mean\)\s*$/im',
            'p50_latency_ms' => '/^\s*50%\s+(\d+)\s*$/m',
            'p95_latency_ms' => '/^\s*95%\s+(\d+)\s*$/m',
            'p99_latency_ms' => '/^\s*99%\s+(\d+)\s*$/m',
        ];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $matches) === 1) {
                $metrics[$key] = $key === 'failed_requests' ? (int) $matches[1] : (float) $matches[1];
            }
        }

        return $metrics;
    }
}
