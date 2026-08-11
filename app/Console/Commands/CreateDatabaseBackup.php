<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PostgreSqlBackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operations:backup {--user= : Optional initiating user UUID}')]
#[Description('Create a checksummed PostgreSQL custom-format backup and evidence record')]
class CreateDatabaseBackup extends Command
{
    public function handle(PostgreSqlBackupManager $manager): int
    {
        $actor = $this->option('user') ? User::query()->findOrFail((string) $this->option('user')) : null;
        $backup = $manager->create($actor);
        $this->line("{$backup->reference} {$backup->status} ".($backup->sha256 ?? 'no-checksum'));

        return $backup->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
