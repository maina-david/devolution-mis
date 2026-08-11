<?php

namespace App\Models;

use Database\Factories\PerformanceTestRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $environment
 * @property string $tool
 * @property string $target_url
 * @property string $route_path
 * @property int $request_count
 * @property int $concurrency
 * @property int $successful_requests
 * @property int $failed_requests
 * @property string|null $requests_per_second
 * @property string|null $mean_latency_ms
 * @property string|null $p50_latency_ms
 * @property string|null $p95_latency_ms
 * @property string|null $p99_latency_ms
 * @property int $duration_ms
 * @property array<string, int|float> $threshold_snapshot
 * @property string $outcome
 * @property string|null $error_category
 * @property string|null $error_detail
 * @property string|null $initiated_by
 * @property string $initiated_by_name
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property string $output_checksum
 * @property string $evidence_checksum
 */
#[Fillable(['environment', 'tool', 'target_url', 'route_path', 'request_count', 'concurrency', 'successful_requests', 'failed_requests', 'requests_per_second', 'mean_latency_ms', 'p50_latency_ms', 'p95_latency_ms', 'p99_latency_ms', 'duration_ms', 'threshold_snapshot', 'outcome', 'error_category', 'error_detail', 'initiated_by', 'initiated_by_name', 'started_at', 'completed_at', 'output_checksum', 'evidence_checksum'])]
class PerformanceTestRun extends Model
{
    /** @use HasFactory<PerformanceTestRunFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['request_count' => 'integer', 'concurrency' => 'integer', 'successful_requests' => 'integer', 'failed_requests' => 'integer', 'requests_per_second' => 'decimal:3', 'mean_latency_ms' => 'decimal:3', 'p50_latency_ms' => 'decimal:3', 'p95_latency_ms' => 'decimal:3', 'p99_latency_ms' => 'decimal:3', 'duration_ms' => 'integer', 'threshold_snapshot' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
