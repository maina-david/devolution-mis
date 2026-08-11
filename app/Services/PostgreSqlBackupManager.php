<?php

namespace App\Services;

use App\Models\OperationalBackup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PostgreSqlBackupManager
{
    public function create(?User $actor = null): OperationalBackup
    {
        $connection = $this->connection();
        $reference = 'BKP-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));
        $path = trim((string) config('operations.backup_path'), '/').'/'.$reference.'.dump';
        $backup = OperationalBackup::create(['initiated_by' => $actor?->id, 'reference' => $reference, 'disk' => config('operations.backup_disk'), 'path' => $path, 'database_name' => $connection['database'], 'format' => 'postgres_custom', 'status' => 'running', 'started_at' => now(), 'metadata' => ['rpo_minutes' => config('operations.rpo_minutes'), 'retention_days' => config('operations.backup_retention_days')]]);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'idmis-backup-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary backup path.');
        }

        try {
            $process = new Process([config('operations.pg_dump_binary'), '--format=custom', '--no-owner', '--no-acl', '--host='.$connection['host'], '--port='.$connection['port'], '--username='.$connection['username'], '--file='.$temporaryPath, $connection['database']], null, $this->processEnvironment($connection), timeout: 1800);
            $process->mustRun();
            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false || ! Storage::disk($backup->disk)->put($path, $stream)) {
                throw new RuntimeException('Unable to persist the database backup on the configured backup disk.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
            $backup->update(['sha256' => hash_file('sha256', $temporaryPath), 'size_bytes' => filesize($temporaryPath), 'status' => 'completed', 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $backup->update(['status' => 'failed', 'completed_at' => now(), 'error_detail' => mb_substr($exception->getMessage(), 0, 5000)]);
        } finally {
            @unlink($temporaryPath);
        }

        return $backup->refresh();
    }

    public function verify(OperationalBackup $backup, ?User $actor = null, bool $restoreProbe = false): OperationalBackup
    {
        abort_unless($backup->status === 'completed' && $backup->sha256, 409, 'Only completed backups can be verified.');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'idmis-restore-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary restore path.');
        }
        $input = Storage::disk($backup->disk)->readStream($backup->path);
        $output = fopen($temporaryPath, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException('Unable to read the backup artifact for verification.');
        }
        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
        $started = hrtime(true);
        $connection = $this->connection();
        $restoreDatabase = 'idmis_restore_probe_'.now()->format('YmdHis').'_'.Str::lower(Str::random(4));

        try {
            $actualChecksum = hash_file('sha256', $temporaryPath);
            abort_unless(is_string($actualChecksum) && hash_equals($backup->sha256, $actualChecksum), 409, 'Backup checksum verification failed.');
            $manifestProcess = new Process([config('operations.pg_restore_binary'), '--list', $temporaryPath], null, $this->processEnvironment($connection), timeout: 300);
            $manifestProcess->mustRun();
            $manifest = $manifestProcess->getOutput();
            $manifestLines = preg_split('/\R/', $manifest);
            abort_unless(is_array($manifestLines), 409, 'Backup manifest could not be parsed.');
            $tableCount = collect($manifestLines)->filter(fn (string $line): bool => str_contains($line, ' TABLE ') && ! str_contains($line, 'TABLE DATA'))->count();
            abort_unless($tableCount > 0, 409, 'Backup manifest contains no application tables.');

            if ($restoreProbe) {
                abort_unless((bool) preg_match('/\Aidmis_restore_probe_[a-z0-9_]+\z/', $restoreDatabase), 500, 'Unsafe restore probe target.');
                $this->databaseProcess(config('operations.createdb_binary'), $connection, [$restoreDatabase])->mustRun();
                try {
                    $restore = new Process([config('operations.pg_restore_binary'), '--no-owner', '--no-acl', '--exit-on-error', '--host='.$connection['host'], '--port='.$connection['port'], '--username='.$connection['username'], '--dbname='.$restoreDatabase, $temporaryPath], null, $this->processEnvironment($connection), timeout: 1800);
                    $restore->mustRun();
                    $probe = $this->databaseProcess(config('operations.psql_binary'), $connection, ['--dbname='.$restoreDatabase, '--tuples-only', '--no-align', '--command=select count(*) from information_schema.tables where table_schema = \'public\''])->mustRun();
                    abort_unless((int) trim($probe->getOutput()) >= $tableCount, 409, 'Restored database table count is below the backup manifest count.');
                } finally {
                    $this->databaseProcess(config('operations.dropdb_binary'), $connection, ['--if-exists', $restoreDatabase])->run();
                }
            }

            $backup->update(['restore_verified_by' => $actor?->id, 'restore_verified_at' => now(), 'restore_duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000), 'verified_table_count' => $tableCount, 'restore_manifest_checksum' => hash('sha256', $manifest), 'metadata' => [...($backup->metadata ?? []), 'restore_probe' => $restoreProbe ? 'database_restored_and_dropped' : 'manifest_only']]);
        } finally {
            @unlink($temporaryPath);
        }

        return $backup->refresh();
    }

    /** @return array{host:string,port:string,username:string,password:string,database:string} */
    private function connection(): array
    {
        $configuration = config('database.connections.'.config('database.default'));
        abort_unless(($configuration['driver'] ?? null) === 'pgsql', 409, 'Operational backup currently requires PostgreSQL.');

        return ['host' => (string) ($configuration['host'] ?? '127.0.0.1'), 'port' => (string) ($configuration['port'] ?? '5432'), 'username' => (string) ($configuration['username'] ?? ''), 'password' => (string) ($configuration['password'] ?? ''), 'database' => (string) ($configuration['database'] ?? '')];
    }

    /** @param array{password:string} $connection
     * @return array<string, string>
     */
    private function processEnvironment(array $connection): array
    {
        return ['PGPASSWORD' => $connection['password']];
    }

    /** @param array{host:string,port:string,username:string,password:string,database:string} $connection
     * @param  list<string>  $arguments
     */
    private function databaseProcess(string $binary, array $connection, array $arguments): Process
    {
        return new Process([$binary, '--host='.$connection['host'], '--port='.$connection['port'], '--username='.$connection['username'], ...$arguments], null, $this->processEnvironment($connection), timeout: 1800);
    }
}
