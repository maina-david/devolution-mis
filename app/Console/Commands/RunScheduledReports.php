<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScheduledReport;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('reports:run-scheduled')]
#[Description('Queue due, independently approved analytics report schedules.')]
class RunScheduledReports extends Command
{
    public function handle(): int
    {
        $queued = 0;
        ReportSchedule::query()
            ->where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->chunkById(100, function ($schedules) use (&$queued): void {
                foreach ($schedules as $schedule) {
                    $run = DB::transaction(function () use ($schedule): ?ReportRun {
                        $locked = ReportSchedule::query()->lockForUpdate()->find($schedule->id);
                        if (! $locked instanceof ReportSchedule || $locked->status !== 'active' || $locked->next_run_at->isFuture()) {
                            return null;
                        }
                        $run = $locked->runs()->create([
                            'status' => 'queued',
                            'filter_snapshot' => $locked->filters,
                            'period_from' => $locked->filters['from'] ?? null,
                            'period_to' => $locked->filters['to'] ?? null,
                        ]);
                        $locked->update(['next_run_at' => match ($locked->frequency) {
                            'daily' => $locked->next_run_at->addDay(),
                            'weekly' => $locked->next_run_at->addWeek(),
                            'monthly' => $locked->next_run_at->addMonthNoOverflow(),
                            default => $locked->next_run_at->addDay(),
                        }]);

                        return $run;
                    });
                    if ($run instanceof ReportRun) {
                        GenerateScheduledReport::dispatch($run);
                        $queued++;
                    }
                }
            });

        $this->components->info("Queued {$queued} scheduled report(s).");

        return self::SUCCESS;
    }
}
