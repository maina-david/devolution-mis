<?php

namespace App\Jobs;

use App\Models\OperationalBackup;
use App\Models\User;
use App\Services\PostgreSqlBackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyOperationalBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public string $backupId, public ?string $userId = null, public bool $restoreProbe = true) {}

    public function handle(PostgreSqlBackupManager $manager): void
    {
        $manager->verify(OperationalBackup::query()->findOrFail($this->backupId), $this->userId ? User::query()->find($this->userId) : null, $this->restoreProbe);
    }
}
