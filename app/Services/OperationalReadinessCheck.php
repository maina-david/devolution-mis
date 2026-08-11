<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OperationalReadinessCheck
{
    public function __construct(private DocumentSecurityScanner $documentSecurityScanner) {}

    /** @return array{ready:bool,checked_at:string,checks:array<string,array{status:string,latency_ms:float|null,detail:string}>} */
    public function run(): array
    {
        $checks = [
            'database' => $this->timed(function (): string {
                DB::select('select 1');

                return 'PostgreSQL query succeeded.';
            }),
            'cache' => $this->timed(function (): string {
                $key = 'operations-readiness-'.Str::uuid();
                Cache::put($key, 'ready', 30);
                abort_unless(Cache::get($key) === 'ready', 503, 'Cache round trip failed.');
                Cache::forget($key);

                return 'Cache write/read/delete succeeded.';
            }),
            'private_storage' => $this->timed(function (): string {
                $path = 'operations/readiness/'.Str::uuid().'.probe';
                abort_unless(Storage::disk(config('operations.backup_disk'))->put($path, 'ready') !== false, 503, 'Private storage write failed.');
                abort_unless(Storage::disk(config('operations.backup_disk'))->get($path) === 'ready', 503, 'Private storage read failed.');
                Storage::disk(config('operations.backup_disk'))->delete($path);

                return 'Private storage write/read/delete succeeded.';
            }),
            'queue' => $this->timed(function (): string {
                abort_unless(Schema::hasTable(config('queue.connections.database.table', 'jobs')), 503, 'Queue table is unavailable.');
                $failed = Schema::hasTable(config('queue.failed.table', 'failed_jobs')) ? DB::table(config('queue.failed.table', 'failed_jobs'))->count() : 0;

                return "Queue persistence is available; {$failed} failed jobs recorded.";
            }),
            'document_malware_scanner' => $this->timed(fn (): string => $this->documentSecurityScanner->readinessDetail()),
        ];

        return ['ready' => collect($checks)->every(fn (array $check): bool => $check['status'] === 'pass'), 'checked_at' => now()->toIso8601String(), 'checks' => $checks];
    }

    /** @param callable(): string $check
     * @return array{status:string,latency_ms:float|null,detail:string}
     */
    private function timed(callable $check): array
    {
        $started = hrtime(true);
        try {
            $detail = $check();

            return ['status' => 'pass', 'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 2), 'detail' => $detail];
        } catch (Throwable $exception) {
            return ['status' => 'fail', 'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 2), 'detail' => mb_substr($exception->getMessage(), 0, 500)];
        }
    }
}
