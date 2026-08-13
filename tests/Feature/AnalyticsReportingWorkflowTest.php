<?php

namespace Tests\Feature;

use App\Jobs\GenerateScheduledReport;
use App\Models\AnalyticsDashboard;
use App\Models\County;
use App\Models\EvaluationFinding;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Programme;
use App\Models\ReferenceDataRelease;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AnalyticsMetricCatalogue;
use App\Services\ScheduledReportGenerator;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AnalyticsReportingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_publication_is_independent_and_metrics_are_county_scoped(): void
    {
        $county = County::factory()->create(['name' => 'Baringo', 'code' => 30]);
        $otherCounty = County::factory()->create(['name' => 'Nakuru', 'code' => 32]);
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $countyViewer = User::factory()->countyAdmin($county)->create();
        $otherViewer = User::factory()->countyAdmin($otherCounty)->create();
        $release = $this->publishedReferenceRelease([$county, $otherCounty], $publisher);

        $this->actingAs($author)->post(route('analytics.dashboards.store'), $this->dashboardPayload($county->id))->assertRedirect();
        $dashboard = AnalyticsDashboard::query()->with('widgets')->sole();
        $this->assertTrue(Str::isUuid($dashboard->id));
        $this->assertSame('draft', $dashboard->status);
        $this->assertSame($release->id, $dashboard->reference_data_release_id);
        $this->assertCount(1, $dashboard->widgets);
        $this->actingAs($author)->patch(route('analytics.dashboards.publish', [$dashboard]))->assertForbidden();
        $this->actingAs($publisher)->patch(route('analytics.dashboards.publish', [$dashboard]))->assertRedirect();
        $this->assertSame('published', $dashboard->refresh()->status);
        $this->assertSame(64, strlen((string) $dashboard->checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $dashboard->id, 'action' => 'analytics.dashboard.published']);

        $this->actingAs($countyViewer)->get(route('analytics.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('dashboards', 1)
            ->where('dashboards.0.county.name', 'Baringo')
            ->where('dashboards.0.referenceData.version', $release->version)
            ->where('dashboards.0.widgets.0.measurement.value', 1));
        $this->actingAs($otherViewer)->get(route('analytics.index'))->assertOk()->assertInertia(fn ($page) => $page->where('dashboards', []));
    }

    public function test_schedules_require_independent_activation_and_due_execution_is_idempotently_queued(): void
    {
        Queue::fake();
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $recipient = User::factory()->countyAdmin($county)->create();
        $dashboard = $this->publishedDashboard($author, $approver, $county);

        $this->actingAs($author)->post(route('analytics.schedules.store'), $this->schedulePayload($dashboard, $county, $recipient))->assertRedirect();
        $schedule = ReportSchedule::query()->sole();
        $this->assertSame('draft', $schedule->status);
        $this->actingAs($author)->patch(route('analytics.schedules.activate', [$schedule]))->assertForbidden();
        $this->assertTrue($schedule->next_run_at->isFuture(), 'The validated first execution should remain in the future.');
        $this->assertSame('published', $dashboard->status);
        $this->assertNotNull($dashboard->checksum);
        $this->actingAs($approver)->patch(route('analytics.schedules.activate', [$schedule]))->assertRedirect();
        $this->assertSame('active', $schedule->refresh()->status);
        $schedule->update(['next_run_at' => now()->subMinute()]);

        $this->assertSame(0, Artisan::call('reports:run-scheduled'));
        $this->assertSame(0, Artisan::call('reports:run-scheduled'));
        $this->assertDatabaseCount('report_runs', 1);
        Queue::assertPushed(GenerateScheduledReport::class, 1);
        $this->assertTrue($schedule->refresh()->next_run_at->isFuture(), 'The due runner should advance the recurrence into the future.');
    }

    public function test_analytics_mutation_and_fail_closed_outcomes_follow_the_active_locale(): void
    {
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $recipient = User::factory()->countyAdmin($county)->create();
        $this->publishedReferenceRelease([$county], $approver);

        $this->actingAs($author)
            ->withSession(['locale' => 'sw'])
            ->post(route('analytics.dashboards.store'), $this->dashboardPayload($county->id))
            ->assertRedirect()
            ->assertSessionHas('success', 'Dashibodi ANL-COUNTY-READINESS imeundwa kama rasimu inayodhibitiwa.');

        $dashboard = AnalyticsDashboard::query()->sole();
        $this->actingAs($approver)
            ->withSession(['locale' => 'fr'])
            ->patch(route('analytics.dashboards.publish', [$dashboard]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Le tableau de bord a été publié indépendamment avec une somme de contrôle de configuration.');

        $schedule = ReportSchedule::factory()->create([
            'created_by' => $author->id,
            'county_id' => $county->id,
            'reference_data_release_id' => $dashboard->reference_data_release_id,
            'filters' => ['dashboard_id' => $dashboard->id],
            'recipient_user_ids' => [$recipient->id],
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->withSession(['locale' => 'sw'])
            ->post(route('analytics.schedules.run', [$schedule]))
            ->assertStatus(409)
            ->assertSeeText('Ratiba hai pekee ndizo zinaweza kuendeshwa.');

        $run = ReportRun::factory()->create([
            'report_schedule_id' => $schedule->id,
            'filter_snapshot' => $schedule->filters,
            'status' => 'queued',
        ]);

        $this->actingAs($recipient)
            ->withSession(['locale' => 'fr'])
            ->get(route('analytics.runs.download', [$run]))
            ->assertStatus(409)
            ->assertSeeText('L’artefact du rapport n’est pas prêt.');
    }

    public function test_governed_dashboards_compose_scoped_me_target_and_follow_up_metrics(): void
    {
        $county = County::factory()->create(['name' => 'Baringo', 'code' => 30]);
        $otherCounty = County::factory()->create(['name' => 'Nakuru', 'code' => 32]);
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $countyViewer = User::factory()->countyAdmin($county)->create();
        $programme = Programme::factory()->create();
        $this->publishedReferenceRelease([$county, $otherCounty], $publisher);
        $indicator = IndicatorDefinition::factory()->create(['direction' => 'increase']);
        $common = [
            'indicator_definition_id' => $indicator->id,
            'programme_id' => $programme->id,
            'county_id' => $county->id,
            'verification_status' => 'verified',
            'quality_status' => 'accepted',
            'dimension_key' => 'total',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
        ];
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'target', 'numeric_value' => 80]);
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'actual', 'numeric_value' => 100]);
        IndicatorObservation::factory()->create([...$common, 'county_id' => $otherCounty->id, 'measure_type' => 'target', 'numeric_value' => 1]);
        IndicatorObservation::factory()->create([...$common, 'county_id' => $otherCounty->id, 'measure_type' => 'actual', 'numeric_value' => 999]);
        EvaluationFinding::factory()->create(['county_id' => $county->id, 'status' => 'open', 'due_at' => today()->subDay()]);
        EvaluationFinding::factory()->create(['county_id' => $county->id, 'status' => 'closed', 'due_at' => today()->subMonth(), 'closed_at' => now()]);
        EvaluationFinding::factory()->create(['county_id' => $otherCounty->id, 'status' => 'open', 'due_at' => today()->subDay()]);

        $this->actingAs($author)->post(route('analytics.dashboards.store'), $this->dashboardPayload($county->id, 'indicators.target-attainment', 'ANL-ME-PERFORMANCE'))->assertRedirect();
        $dashboard = AnalyticsDashboard::query()->sole();
        $this->actingAs($publisher)->patch(route('analytics.dashboards.publish', [$dashboard]))->assertRedirect();

        $this->actingAs($countyViewer)->get(route('analytics.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('dashboards.0.widgets.0.measurement.value', 125)
            ->where('dashboards.0.widgets.0.measurement.unit', 'percent')
            ->where('dashboards.0.widgets.0.measurement.series.0.county.name', 'Baringo')
            ->where('dashboards.0.widgets.0.measurement.series.0.value', 125));

        $catalogue = app(AnalyticsMetricCatalogue::class);
        $this->assertSame(1, $catalogue->evaluate($countyViewer, 'evaluation-findings.overdue')['value']);
        $this->assertSame(1, $catalogue->evaluate($countyViewer, 'evaluation-findings.closed')['value']);
        $this->assertArrayHasKey('indicators.target-attainment', $catalogue->options());
        $this->assertArrayHasKey('evaluation-findings.overdue', $catalogue->options());
    }

    public function test_metric_catalogue_produces_bounded_authorized_time_series(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $viewer = User::factory()->countyAdmin($county)->create();
        EvaluationFinding::factory()->create(['county_id' => $county->id, 'status' => 'closed', 'closed_at' => '2026-01-15']);
        EvaluationFinding::factory()->create(['county_id' => $county->id, 'status' => 'closed', 'closed_at' => '2026-04-15']);
        EvaluationFinding::factory()->create(['county_id' => $otherCounty->id, 'status' => 'closed', 'closed_at' => '2026-04-15']);

        $measurement = app(AnalyticsMetricCatalogue::class)->evaluate($viewer, 'evaluation-findings.closed', [
            'from' => '2026-01-01',
            'to' => '2026-06-30',
            'time_grain' => 'quarter',
        ]);

        $this->assertSame(['Q1 2026', 'Q2 2026'], array_column($measurement['trend'], 'label'));
        $this->assertSame([1, 1], array_column($measurement['trend'], 'value'));
        $this->assertCount(2, $measurement['trend']);
    }

    public function test_all_formats_are_private_checksummed_and_download_revalidates_integrity(): void
    {
        Notification::fake();
        Storage::fake('local');
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->platformAdmin()->create();
        $recipient = User::factory()->countyAdmin($county)->create();
        $dashboard = $this->publishedDashboard($author, $approver, $county);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $schedule = ReportSchedule::factory()->create([
                'created_by' => $author->id,
                'approved_by' => $approver->id,
                'county_id' => $county->id,
                'reference_data_release_id' => $dashboard->reference_data_release_id,
                'workspace' => 'analytics-dashboard',
                'format' => $format,
                'frequency' => 'monthly',
                'filters' => ['dashboard_id' => $dashboard->id],
                'recipient_user_ids' => [$recipient->id],
                'status' => 'active',
                'approved_at' => now(),
            ]);
            $run = ReportRun::factory()->create(['report_schedule_id' => $schedule->id, 'filter_snapshot' => $schedule->filters]);
            app(ScheduledReportGenerator::class)->generate($run);
            $run->refresh();
            $this->assertSame('completed', $run->status);
            $this->assertSame(64, strlen((string) $run->sha256));
            $this->assertGreaterThan(0, $run->size_bytes);
            Storage::disk('local')->assertExists((string) $run->path);
            if ($format === 'json') {
                $contents = Storage::disk('local')->get((string) $run->path);
                $this->assertStringContainsString('"unit": "records"', $contents);
                $this->assertStringContainsString('"kind": "county"', $contents);
                $this->assertStringContainsString('"name": "'.$county->name.'"', $contents);
                $this->assertStringContainsString('"dashboard_reference_release"', $contents);
                $this->assertStringContainsString('"schedule_reference_checksum"', $contents);
                $this->assertStringContainsString('"visualization": "metric"', $contents);
                $this->assertStringContainsString('"trend": []', $contents);
            }
            if ($format === 'csv') {
                $contents = Storage::disk('local')->get((string) $run->path);
                $this->assertStringContainsString('Visualization,"Time grain",Trend', $contents);
            }
        }

        Notification::assertSentTo($recipient, ProgrammeAlert::class, function (ProgrammeAlert $notification): bool {
            app()->setLocale('fr');
            $content = $notification->toArray(new \stdClass);

            return $notification->titleTranslationKey === 'analytics.report_generator.notifications.ready_title'
                && $content['title'] === __('analytics.report_generator.notifications.ready_title')
                && $content['message'] === __('analytics.report_generator.notifications.ready_message', ['name' => $notification->messageTranslationParameters['name']]);
        });

        $downloadable = ReportRun::query()->latest()->firstOrFail();
        $this->actingAs($recipient)->get(route('analytics.runs.download', [$downloadable]))->assertOk()->assertDownload();
        Storage::disk('local')->put((string) $downloadable->path, 'tampered');
        $this->actingAs($recipient)->get(route('analytics.runs.download', [$downloadable]))->assertStatus(409);
    }

    public function test_report_generator_failures_and_catalogues_follow_the_active_locale(): void
    {
        $schedule = ReportSchedule::factory()->create(['filters' => ['dashboard_id' => (string) Str::uuid()]]);
        $run = ReportRun::factory()->create(['report_schedule_id' => $schedule->id]);
        app()->setLocale('sw');

        try {
            app(ScheduledReportGenerator::class)->generate($run);
            $this->fail('A scheduled report without an executable governed dashboard must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('analytics.report_generator.errors.configuration_unavailable'), $exception->getMessage());
        }

        $english = require lang_path('en/analytics.php');
        $kiswahili = require lang_path('sw/analytics.php');
        $french = require lang_path('fr/analytics.php');

        foreach (['errors', 'audit', 'notifications'] as $section) {
            $this->assertSame(array_keys($english['report_generator'][$section]), array_keys($kiswahili['report_generator'][$section]));
            $this->assertSame(array_keys($english['report_generator'][$section]), array_keys($french['report_generator'][$section]));
        }
    }

    public function test_analytics_configuration_fails_closed_without_valid_catalogue_lineage(): void
    {
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $payload = $this->dashboardPayload($county->id, code: 'ANL-CATALOGUE-CONTROL');

        $this->actingAs($author)->post(route('analytics.dashboards.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('analytics_dashboards', 0);

        $corruptSnapshot = ['counties' => [['id' => $county->id, 'code' => $county->code, 'name' => $county->name]], 'organizations' => [], 'sectors' => [], 'programmes' => []];
        ReferenceDataRelease::factory()->create(['approved_by' => $publisher->id, 'status' => 'published', 'snapshot' => $corruptSnapshot, 'checksum' => str_repeat('0', 64), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
        $this->actingAs($author)->post(route('analytics.dashboards.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('analytics_dashboards', 0);

        $validButIncomplete = ['counties' => [], 'organizations' => [], 'sectors' => [], 'programmes' => []];
        ReferenceDataRelease::factory()->create(['approved_by' => $publisher->id, 'status' => 'published', 'snapshot' => $validButIncomplete, 'checksum' => app(CanonicalJson::class)->checksum($validButIncomplete), 'effective_from' => now(), 'published_at' => now()]);
        $this->actingAs($author)->post(route('analytics.dashboards.store'), $payload)->assertSessionHasErrors('county_id');
        $this->assertDatabaseCount('analytics_dashboards', 0);
    }

    private function publishedDashboard(User $author, User $publisher, County $county): AnalyticsDashboard
    {
        if (! ReferenceDataRelease::query()->where('status', 'published')->where('effective_from', '<=', now())->exists()) {
            $this->publishedReferenceRelease([$county], $publisher);
        }
        $this->actingAs($author)->post(route('analytics.dashboards.store'), $this->dashboardPayload($county->id))->assertRedirect();
        $dashboard = AnalyticsDashboard::query()->latest()->firstOrFail();
        $this->actingAs($publisher)->patch(route('analytics.dashboards.publish', [$dashboard]))->assertRedirect();

        return $dashboard->refresh();
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id, 'code' => $county->code, 'name' => $county->name])->values()->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
        ];

        return ReferenceDataRelease::factory()->create([
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(string $countyId, string $metricKey = 'counties.total', string $code = 'ANL-COUNTY-READINESS'): array
    {
        return [
            'code' => $code,
            'name' => 'County readiness evidence',
            'description' => 'Governed county-scoped readiness measures for operational decision support.',
            'county_id' => $countyId,
            'audience_roles' => ['county-admin', 'devolution-admin', 'platform-admin'],
            'widgets' => [[
                'title' => 'Counties in authorized scope',
                'metric_key' => $metricKey,
                'visualization' => 'metric',
                'disaggregation' => 'county',
                'filters' => [],
                'position' => 1,
                'width' => 1,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function schedulePayload(AnalyticsDashboard $dashboard, County $county, User $recipient): array
    {
        return [
            'code' => 'RPT-COUNTY-READINESS',
            'name' => 'Monthly county readiness evidence',
            'workspace' => 'analytics-dashboard',
            'county_id' => $county->id,
            'format' => 'pdf',
            'frequency' => 'monthly',
            'filters' => ['dashboard_id' => $dashboard->id, 'from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()],
            'recipient_user_ids' => [$recipient->id],
            'next_run_at' => now()->addDay()->toIso8601String(),
        ];
    }
}
