<?php

namespace App\Jobs;

use App\Models\ReportRun;
use App\Services\ScheduledReportGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateScheduledReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public ReportRun $run)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->run->id;
    }

    public function handle(ScheduledReportGenerator $generator): void
    {
        $generator->generate($this->run);
    }

    public function failed(Throwable $exception): void
    {
        $this->run->refresh()->update([
            'status' => 'failed',
            'error_detail' => str($exception->getMessage())->limit(1000),
            'completed_at' => now(),
        ]);
    }
}
