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
        abort_unless($schedule->status === 'draft', 409, __('analytics.errors.draft_schedule_required'));
        if ($schedule->created_by === $actor->id) {
            throw new HttpException(403, __('analytics.errors.schedule_author_separation'));
        }
        abort_if($schedule->next_run_at->isPast(), 409, __('analytics.errors.future_first_run_required'));
        $dashboardId = $schedule->filters['dashboard_id'] ?? null;
        $dashboard = is_string($dashboardId) ? AnalyticsDashboard::query()->find($dashboardId) : null;
        abort_unless($dashboard instanceof AnalyticsDashboard && $dashboard->status === 'published' && $dashboard->checksum !== null, 409, __('analytics.errors.dashboard_published_checksummed'));
        $schedule->update(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->auditLogger->record($actor, $schedule, 'analytics.report-schedule.activated', __('analytics.audit.schedule_activated', ['code' => $schedule->code]), $schedule->county_id);

        return $schedule;
    }
}
