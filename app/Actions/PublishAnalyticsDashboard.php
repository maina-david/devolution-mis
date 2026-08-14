<?php

namespace App\Actions;

use App\Models\AnalyticsDashboard;
use App\Models\User;
use App\Services\AuditLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublishAnalyticsDashboard
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AnalyticsDashboard $dashboard, User $actor): AnalyticsDashboard
    {
        abort_unless($dashboard->status === 'draft', 409, __('analytics.errors.draft_dashboard_required'));
        if ($dashboard->created_by === $actor->id) {
            throw new HttpException(403, __('analytics.errors.dashboard_author_separation'));
        }
        $dashboard->load('widgets');
        abort_if($dashboard->widgets->isEmpty(), 409, __('analytics.errors.governed_widget_required'));
        $snapshot = ['code' => $dashboard->code, 'county_id' => $dashboard->county_id, 'audience_roles' => $dashboard->audience_roles, 'widgets' => $dashboard->widgets->sortBy('position')->map->only(['title', 'metric_key', 'visualization', 'disaggregation', 'filters', 'position', 'width'])->values()->all()];
        $dashboard->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now(), 'checksum' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))]);
        $this->auditLogger->record($actor, $dashboard, 'analytics.dashboard.published', __('analytics.audit.dashboard_published', ['code' => $dashboard->code]), $dashboard->county_id, ['checksum' => $dashboard->checksum]);

        return $dashboard;
    }
}
