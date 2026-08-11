<?php

namespace Database\Seeders;

use App\Actions\CreateAnalyticsDashboard;
use App\Actions\CreateReportSchedule;
use App\Actions\PublishAnalyticsDashboard;
use App\Enums\UserRole;
use App\Models\AnalyticsDashboard;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnalyticsReportingSeeder extends Seeder
{
    public function run(CreateAnalyticsDashboard $createDashboard, PublishAnalyticsDashboard $publishDashboard, CreateReportSchedule $createSchedule): void
    {
        if (! app()->isLocal() || AnalyticsDashboard::query()->exists()) {
            return;
        }
        $author = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $publisher = User::query()->where('email', 'platform.admin@idmis.test')->first();
        if (! $author || ! $publisher) {
            return;
        }

        $dashboard = $createDashboard->handle($author, [
            'code' => 'ANL-ENGINEERING-BASELINE',
            'name' => 'IDMIS engineering evidence baseline',
            'description' => 'An engineering-only dashboard configuration demonstrating governed, county-scoped aggregation. Values are live system records and are not business acceptance or predictive analytics.',
            'county_id' => null,
            'audience_roles' => collect(UserRole::cases())->map->value->all(),
            'widgets' => [
                ['title' => 'Counties in authorized scope', 'description' => 'The county scope is applied before aggregation.', 'metric_key' => 'counties.total', 'visualization' => 'metric', 'disaggregation' => 'county', 'filters' => [], 'position' => 1, 'width' => 1],
                ['title' => 'Active project records', 'description' => 'Open project records in the current user county or portfolio scope.', 'metric_key' => 'projects.active', 'visualization' => 'bar', 'disaggregation' => 'county', 'filters' => [], 'position' => 2, 'width' => 2],
                ['title' => 'Published assessment results', 'description' => 'Immutable assessment publications in authorized scope.', 'metric_key' => 'assessments.published', 'visualization' => 'metric', 'disaggregation' => null, 'filters' => [], 'position' => 3, 'width' => 1],
            ],
        ]);
        $publishDashboard->handle($dashboard, $publisher);
        $createSchedule->handle($author, [
            'code' => 'RPT-ENGINEERING-BASELINE',
            'name' => 'Proposed monthly engineering evidence report',
            'workspace' => 'analytics-dashboard',
            'county_id' => null,
            'format' => 'pdf',
            'frequency' => 'monthly',
            'filters' => ['dashboard_id' => $dashboard->id],
            'recipient_user_ids' => [$publisher->id],
            'next_run_at' => now()->addMonth()->startOfMonth()->setTime(8, 0),
        ]);
    }
}
