<?php

namespace App\Actions;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsWidget;
use App\Models\User;
use App\Services\AuditLogger;

class AddAnalyticsWidget
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(AnalyticsDashboard $dashboard, User $actor, array $attributes): AnalyticsWidget
    {
        abort_unless($dashboard->status === 'draft', 409, __('analytics.errors.published_dashboard_immutable'));
        $widget = $dashboard->widgets()->create($attributes);
        $this->auditLogger->record($actor, $dashboard, 'analytics.widget.created', __('analytics.audit.widget_created', ['widget' => $widget->title, 'dashboard' => $dashboard->code]), $dashboard->county_id, ['widget_id' => $widget->id, 'metric_key' => $widget->metric_key]);

        return $widget;
    }
}
