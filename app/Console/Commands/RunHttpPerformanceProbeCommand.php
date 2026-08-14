<?php

namespace App\Console\Commands;

use App\Actions\RunHttpPerformanceProbe;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('operations:performance-probe {--base-url= : Approved HTTPS environment base URL} {--path=/up : Allowlisted public route} {--requests=100 : Total requests} {--concurrency=10 : Concurrent requests} {--user= : Optional initiating user UUID}')]
#[Description('Run a bounded same-environment HTTP concurrency probe and retain immutable evidence')]
class RunHttpPerformanceProbeCommand extends Command
{
    public function handle(RunHttpPerformanceProbe $probe): int
    {
        $baseUrl = $this->option('base-url') ?: config('operations.performance.base_url');
        $path = $this->option('path');
        $userId = $this->option('user');
        $user = is_string($userId) && $userId !== '' ? User::query()->find($userId) : null;

        if (! is_string($baseUrl) || ! is_string($path)) {
            $this->error((string) __('operations.performance.errors.base_url_required'));

            return self::INVALID;
        }

        try {
            $run = $probe->handle($baseUrl, $path, (int) $this->option('requests'), (int) $this->option('concurrency'), $user);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->table([
            __('operations.performance.cli.evidence'),
            __('operations.performance.cli.outcome'),
            __('operations.performance.cli.requests_per_second'),
            __('operations.performance.cli.p95_ms'),
            __('operations.performance.cli.failures'),
            __('operations.performance.cli.checksum'),
        ], [[
            $run->id,
            $run->outcome,
            $run->requests_per_second ?? __('operations.performance.cli.unavailable'),
            $run->p95_latency_ms ?? __('operations.performance.cli.unavailable'),
            $run->failed_requests,
            $run->evidence_checksum,
        ]]);

        return $run->outcome === 'pass' ? self::SUCCESS : self::FAILURE;
    }
}
