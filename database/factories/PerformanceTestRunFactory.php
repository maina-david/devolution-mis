<?php

namespace Database\Factories;

use App\Models\PerformanceTestRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PerformanceTestRun>
 */
class PerformanceTestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment' => 'testing',
            'tool' => 'apachebench',
            'target_url' => 'https://devolution-mis.test/up',
            'route_path' => '/up',
            'request_count' => 100,
            'concurrency' => 10,
            'successful_requests' => 100,
            'failed_requests' => 0,
            'requests_per_second' => '40.000',
            'mean_latency_ms' => '250.000',
            'p50_latency_ms' => '200.000',
            'p95_latency_ms' => '450.000',
            'p99_latency_ms' => '500.000',
            'duration_ms' => 2500,
            'threshold_snapshot' => ['minimum_requests_per_second' => 10, 'maximum_p95_latency_ms' => 1000, 'maximum_failed_requests' => 0],
            'outcome' => 'pass',
            'initiated_by_name' => 'system:performance-probe',
            'started_at' => now()->subSeconds(3),
            'completed_at' => now(),
            'output_checksum' => hash('sha256', fake()->uuid()),
            'evidence_checksum' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
