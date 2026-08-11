<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PostgreSqlBackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateOperationalBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public ?string $userId = null) {}

    public function handle(PostgreSqlBackupManager $manager): void
    {
        $manager->create($this->userId ? User::query()->find($this->userId) : null);
    }
}
