<?php

namespace App\Console\Commands;

use App\Models\OperationalBackup;
use App\Models\User;
use App\Services\PostgreSqlBackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operations:verify-backup {backup? : Backup UUID or latest completed backup} {--restore-probe : Restore into a generated temporary database and drop it after verification} {--user= : Optional verifying user UUID}')]
#[Description('Verify backup integrity and optionally perform an isolated temporary-database restore probe')]
class VerifyDatabaseBackup extends Command
{
    public function handle(PostgreSqlBackupManager $manager): int
    {
        $backup = $this->argument('backup') ? OperationalBackup::query()->findOrFail((string) $this->argument('backup')) : OperationalBackup::query()->where('status', 'completed')->latest('completed_at')->firstOrFail();
        $actor = $this->option('user') ? User::query()->findOrFail((string) $this->option('user')) : null;
        $backup = $manager->verify($backup, $actor, (bool) $this->option('restore-probe'));
        $this->line("{$backup->reference} verified tables={$backup->verified_table_count} duration_ms={$backup->restore_duration_ms}");

        return self::SUCCESS;
    }
}
