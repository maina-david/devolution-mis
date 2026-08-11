<?php

namespace App\Actions;

use App\Models\AnalyticsDashboard;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ActivateReportSchedule
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ReportSchedule $schedule, User $actor): ReportSchedule
    {
        abort_unless($schedule->status === 'draft', 409, 'Only draft schedules can be activated.');
        if ($schedule->created_by === $actor->id) {
            throw new HttpException(403, 'Schedule authors cannot independently activate report delivery.');
        }
        abort_if($schedule->next_run_at->isPast(), 409, 'The first run must be rescheduled into the future before activation.');
        $dashboardId = $schedule->filters['dashboard_id'] ?? null;
        $dashboard = is_string($dashboardId) ? AnalyticsDashboard::query()->find($dashboardId) : null;
        abort_unless($dashboard instanceof AnalyticsDashboard && $dashboard->status === 'published' && $dashboard->checksum !== null, 409, 'The governed dashboard must remain published and checksummed.');
        $schedule->update(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->auditLogger->record($actor, $schedule, 'analytics.report-schedule.activated', "Scheduled report {$schedule->code} independently activated.", $schedule->county_id);

        return $schedule;
    }
}
