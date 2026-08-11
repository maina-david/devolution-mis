<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssessmentCycle;
use App\Models\County;
use App\Models\IdentityLifecycleRequest;
use App\Models\PerformanceTestRun;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProgrammeAuthorization;
use App\Support\CanonicalJson;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

class PerformanceAssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_volume_project_register_has_bounded_queries_and_latency_for_national_and_county_scope(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create();
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $sector = Sector::factory()->create();
        $this->insertProjects(array_values($counties->all()), $sector->id, $nationalUser->id, 2000);

        $this->actingAs($nationalUser)->get(route('projects.index', $nationalUser->currentTeam->slug))->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get(route('projects.index', $nationalUser->currentTeam->slug)));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('projects.total', 2000)->has('projects.data', 15));
        $this->assertLessThanOrEqual(25, $nationalQueries, "National project register used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National project register took {$nationalMilliseconds} ms at reference volume.");

        $expectedCountyProjects = (int) ceil(2000 / 47);
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get(route('projects.index', $countyUser->currentTeam->slug)));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('projects.total', $expectedCountyProjects)->has('projects.data', 15));
        $this->assertLessThanOrEqual(25, $countyQueries, "County project register used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County project register took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_reference_volume_global_search_has_bounded_queries_latency_and_county_isolation(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $portfolioCounty = $counties->last();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($portfolioCounty);
        $sector = Sector::factory()->create();
        $this->insertProjects(array_values($counties->all()), $sector->id, $nationalUser->id, 2000, 'Discovery benchmark');

        $nationalUrl = route('search.global', ['current_team' => $nationalUser->currentTeam->slug, 'q' => 'Discovery benchmark']);
        $this->actingAs($nationalUser)->getJson($nationalUrl)->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->getJson($nationalUrl));
        $nationalResponse->assertOk()->assertJsonCount(5, 'results')->assertJsonPath('results.0.category', 'Projects');
        $this->assertLessThanOrEqual(60, $nationalQueries, "National global search used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National global search took {$nationalMilliseconds} ms at reference volume.");

        $countyUrl = route('search.global', ['current_team' => $countyUser->currentTeam->slug, 'q' => 'Discovery benchmark']);
        $this->actingAs($countyUser)->getJson($countyUrl)->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->getJson($countyUrl));
        $countyResponse->assertOk()->assertJsonCount(5, 'results')->assertJsonPath('results.0.category', 'Projects');
        $authorizedProjectIds = DB::table('devolution_project_county')->where('county_id', $homeCounty->id)->pluck('devolution_project_id')->all();
        foreach ($countyResponse->json('results') as $result) {
            $this->assertContains($result['id'], $authorizedProjectIds);
        }
        $this->assertLessThanOrEqual(60, $countyQueries, "County global search used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County global search took {$countyMilliseconds} ms at reference volume.");

        $portfolioUrl = route('search.global', ['current_team' => $assessor->currentTeam->slug, 'q' => 'Discovery benchmark']);
        $this->actingAs($assessor)->getJson($portfolioUrl)->assertOk();
        [$portfolioResponse, $portfolioQueries, $portfolioMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($assessor)->getJson($portfolioUrl));
        $portfolioResponse->assertOk()->assertJsonCount(5, 'results')->assertJsonPath('results.0.category', 'Projects');
        $portfolioProjectIds = DB::table('devolution_project_county')->where('county_id', $portfolioCounty->id)->pluck('devolution_project_id')->all();
        foreach ($portfolioResponse->json('results') as $result) {
            $this->assertContains($result['id'], $portfolioProjectIds);
        }
        $this->assertLessThanOrEqual(60, $portfolioQueries, "Assigned-portfolio global search used {$portfolioQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $portfolioMilliseconds, "Assigned-portfolio global search took {$portfolioMilliseconds} ms at reference volume.");
    }

    public function test_reference_volume_repository_search_has_bounded_queries_latency_and_county_isolation(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $this->insertRepositoryVolume(array_values($counties->all()), $nationalUser->id, 4700);

        $nationalUrl = route('evidence.index', ['current_team' => $nationalUser->currentTeam->slug, 'search' => 'repository benchmark']);
        $this->actingAs($nationalUser)->get($nationalUrl)->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get($nationalUrl));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workspace.pagination.total', 4700)
            ->has('workspace.rows', 15));
        $this->assertLessThanOrEqual(35, $nationalQueries, "National repository search used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National repository search took {$nationalMilliseconds} ms at reference volume.");

        $countyUrl = route('evidence.index', ['current_team' => $countyUser->currentTeam->slug, 'search' => 'repository benchmark']);
        $this->actingAs($countyUser)->get($countyUrl)->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get($countyUrl));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('workspace.pagination.total', 100)
            ->has('workspace.rows', 15)
            ->where('workspace.rows.0.cells.1.id', $homeCounty->id));
        foreach ($countyResponse->viewData('page')['props']['workspace']['rows'] as $row) {
            $this->assertSame($homeCounty->id, $row['cells'][1]['id']);
        }
        $this->assertLessThanOrEqual(35, $countyQueries, "County repository search used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County repository search took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_training_baseline_identity_lifecycle_batch_is_bounded_complete_and_idempotent(): void
    {
        $requester = User::factory()->devolutionAdmin()->create();
        $decider = User::factory()->platformAdmin()->create();
        $service = User::factory()->platformAdmin()->create();
        config()->set('security-governance.identity_lifecycle_service_user_email', $service->email);
        $assessorRole = app(ProgrammeAuthorization::class)->ensureRole(UserRole::Assessor);
        $targetUserIds = $this->insertIdentityLifecycleVolume($requester->id, $decider->id, $assessorRole->id, 336);

        [$firstExitCode, $firstQueries, $firstMilliseconds] = $this->measureOperation(fn (): int => Artisan::call('security:apply-due-identity-lifecycle', ['--limit' => 250]));
        $this->assertSame(0, $firstExitCode);
        $this->assertStringContainsString('Applied 250', Artisan::output());
        $this->assertSame(250, IdentityLifecycleRequest::query()->where('status', 'applied')->count());

        [$secondExitCode, $secondQueries, $secondMilliseconds] = $this->measureOperation(fn (): int => Artisan::call('security:apply-due-identity-lifecycle', ['--limit' => 250]));
        $this->assertSame(0, $secondExitCode);
        $this->assertStringContainsString('Applied 86', Artisan::output());
        $this->assertSame(336, IdentityLifecycleRequest::query()->where('status', 'applied')->count());
        $this->assertSame(336, IdentityLifecycleRequest::query()->sum('application_attempts'));
        $this->assertSame(336, User::query()->whereIn('id', $targetUserIds)->whereNotNull('access_revoked_at')->count());
        $this->assertSame(0, DB::table('model_has_roles')->whereIn('model_uuid', $targetUserIds)->count());
        $this->assertLessThanOrEqual(10000, $firstQueries + $secondQueries, 'The 336-event identity lifecycle batch exceeded the bounded query budget.');
        $this->assertLessThanOrEqual(30000, $firstMilliseconds + $secondMilliseconds, 'The 336-event identity lifecycle batch exceeded the 30-second local processing ceiling.');

        $this->assertSame(0, Artisan::call('security:apply-due-identity-lifecycle', ['--limit' => 250]));
        $this->assertStringContainsString('Applied 0', Artisan::output());
        $this->assertSame(336, IdentityLifecycleRequest::query()->sum('application_attempts'));
    }

    public function test_selective_discovery_plans_use_postgresql_gin_and_county_portfolio_indexes(): void
    {
        $owner = User::factory()->devolutionAdmin()->create();
        $counties = County::factory()->count(47)->sequence(fn ($sequence): array => ['code' => $sequence->index + 1])->create()->values();
        $sector = Sector::factory()->create();
        $this->insertProjects(array_values($counties->all()), $sector->id, $owner->id, 50000, 'Discovery benchmark');
        $this->insertRepositoryVolume(array_values($counties->all()), $owner->id, 4700);
        DB::statement('ANALYZE devolution_projects');
        DB::statement('ANALYZE devolution_project_county');
        DB::statement('ANALYZE document_extractions');

        $projectPlan = $this->explainAnalyze(
            'SELECT id FROM devolution_projects WHERE title ILIKE ? LIMIT 15',
            ['%Discovery benchmark 49999%'],
        );
        $this->assertTrue(
            str_contains($projectPlan['json'], 'devolution_projects_title_trgm_index'),
            'Selective project discovery did not use the title trigram index.',
        );
        $this->assertLessThanOrEqual(250, $projectPlan['executionMilliseconds']);

        $portfolioPlan = $this->explainAnalyze(
            'SELECT count(*) FROM devolution_project_county WHERE county_id = ?',
            [$counties->first()->id],
        );
        $this->assertTrue(
            str_contains($portfolioPlan['json'], 'devolution_project_county_county_project_index'),
            'Complete county portfolio discovery did not use the county-leading project index.',
        );
        $this->assertLessThanOrEqual(250, $portfolioPlan['executionMilliseconds']);

        $repositoryPlan = $this->explainAnalyze(
            "SELECT document_version_id FROM document_extractions WHERE to_tsvector('simple', coalesce(extracted_text, '')) @@ websearch_to_tsquery('simple', ?) LIMIT 15",
            ['repository benchmark 4700'],
        );
        $this->assertTrue(
            str_contains($repositoryPlan['json'], 'document_extractions_text_search_idx'),
            'Selective repository discovery did not use the extraction full-text index.',
        );
        $this->assertLessThanOrEqual(250, $repositoryPlan['executionMilliseconds']);
    }

    public function test_reference_volume_innovation_replication_register_has_bounded_queries_and_county_isolation(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $snapshot = [
            'counties' => $counties->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $release = ReferenceDataRelease::factory()->create([
            'approved_by' => $nationalUser->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
        $this->insertInnovationReplications(array_values($counties->all()), $nationalUser->id, $release->id, 940);

        $this->actingAs($nationalUser)->get(route('knowledge.innovation-replications.index', $nationalUser->currentTeam->slug))->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get(route('knowledge.innovation-replications.index', $nationalUser->currentTeam->slug)));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('replications.total', 940)
            ->where('summary.total', 940)
            ->has('replications.data', 10));
        $this->assertLessThanOrEqual(25, $nationalQueries, "National innovation replication register used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National innovation replication register took {$nationalMilliseconds} ms at reference volume.");

        $this->actingAs($countyUser)->get(route('knowledge.innovation-replications.index', $countyUser->currentTeam->slug))->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get(route('knowledge.innovation-replications.index', $countyUser->currentTeam->slug)));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('replications.total', 20)
            ->where('summary.total', 20)
            ->has('replications.data', 10)
            ->where('replications.data.0.targetCounty.id', $homeCounty->id));
        $this->assertLessThanOrEqual(25, $countyQueries, "County innovation replication register used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County innovation replication register took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_ten_cycle_forty_seven_county_assessment_analytics_has_bounded_queries_latency_and_scope(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $cycles = collect(range(2017, 2026))->map(fn (int $year): AssessmentCycle => AssessmentCycle::factory()->create([
            'code' => "ACPA-{$year}",
            'name' => "Annual County Performance Assessment {$year}",
            'period_start' => "{$year}-01-01",
            'period_end' => "{$year}-12-31",
        ]));
        $this->insertAssessmentPublications(array_values($counties->all()), array_values($cycles->all()), $nationalUser->id);

        $this->actingAs($nationalUser)->get(route('assessments.analytics.index', $nationalUser->currentTeam->slug))->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get(route('assessments.analytics.index', $nationalUser->currentTeam->slug)));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.publications', 470)
            ->where('report.summary.counties', 47)
            ->where('report.summary.cycles', 10)
            ->has('report.counties', 47)
            ->has('report.cycles', 10)
            ->has('report.rankings.rows', 10));
        $this->assertLessThanOrEqual(20, $nationalQueries, "National assessment analytics used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National assessment analytics took {$nationalMilliseconds} ms at reference volume.");

        $this->actingAs($countyUser)->get(route('assessments.analytics.index', $countyUser->currentTeam->slug))->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get(route('assessments.analytics.index', $countyUser->currentTeam->slug)));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.publications', 10)
            ->where('report.summary.counties', 1)
            ->where('report.summary.cycles', 10)
            ->has('report.counties', 1)
            ->where('report.counties.0.county.id', $homeCounty->id)
            ->has('report.rankings.rows', 1));
        $this->assertLessThanOrEqual(20, $countyQueries, "County assessment analytics used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County assessment analytics took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_reference_volume_learning_analytics_has_bounded_queries_latency_and_county_isolation(): void
    {
        config()->set('analytics.minimum_aggregate_cell_size', 5);
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $this->insertLearningAnalyticsVolume(array_values($counties->all()), $nationalUser);

        $this->actingAs($nationalUser)->get(route('learning.analytics.index', $nationalUser->currentTeam->slug))->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get(route('learning.analytics.index', $nationalUser->currentTeam->slug)));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.enrollments', 9400)
            ->has('report.courses.rows', 10)
            ->where('report.courses.pagination.total', 20)
            ->has('report.counties.rows', 10)
            ->where('report.counties.pagination.total', 47));
        $this->assertLessThanOrEqual(20, $nationalQueries, "National learning analytics used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National learning analytics took {$nationalMilliseconds} ms at reference volume.");

        $this->actingAs($countyUser)->get(route('learning.analytics.index', $countyUser->currentTeam->slug))->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get(route('learning.analytics.index', $countyUser->currentTeam->slug)));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.enrollments', 200)
            ->has('report.counties.rows', 1)
            ->where('report.counties.rows.0.county.id', $homeCounty->id));
        $this->assertLessThanOrEqual(20, $countyQueries, "County learning analytics used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County learning analytics took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_reference_volume_community_analytics_has_bounded_queries_latency_and_county_isolation(): void
    {
        $nationalUser = User::factory()->devolutionAdmin()->create();
        $homeCounty = County::factory()->create(['code' => 1]);
        $counties = collect([$homeCounty])
            ->merge(County::factory()->count(46)->sequence(fn ($sequence): array => ['code' => $sequence->index + 2])->create())
            ->values();
        $countyUser = User::factory()->countyAdmin($homeCounty)->create();
        $this->insertKnowledgeCommunityVolume(array_values($counties->all()), $nationalUser->id);

        $this->actingAs($nationalUser)->get(route('knowledge.community-analytics.index', $nationalUser->currentTeam->slug))->assertOk();
        [$nationalResponse, $nationalQueries, $nationalMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($nationalUser)->get(route('knowledge.community-analytics.index', $nationalUser->currentTeam->slug)));
        $nationalResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.discussions', 940)
            ->where('report.summary.contributions', 3760)
            ->where('report.counties.pagination.total', 47)
            ->where('report.discussions.pagination.total', 940)
            ->has('report.discussions.rows', 10));
        $this->assertLessThanOrEqual(20, $nationalQueries, "National community analytics used {$nationalQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $nationalMilliseconds, "National community analytics took {$nationalMilliseconds} ms at reference volume.");

        $this->actingAs($countyUser)->get(route('knowledge.community-analytics.index', $countyUser->currentTeam->slug))->assertOk();
        [$countyResponse, $countyQueries, $countyMilliseconds] = $this->measure(fn (): TestResponse => $this->actingAs($countyUser)->get(route('knowledge.community-analytics.index', $countyUser->currentTeam->slug)));
        $countyResponse->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('report.summary.discussions', 20)
            ->where('report.summary.contributions', 80)
            ->has('report.counties.rows', 1)
            ->where('report.counties.rows.0.county.id', $homeCounty->id)
            ->where('report.discussions.pagination.total', 20));
        $this->assertLessThanOrEqual(20, $countyQueries, "County community analytics used {$countyQueries} database queries at reference volume.");
        $this->assertLessThanOrEqual(3000, $countyMilliseconds, "County community analytics took {$countyMilliseconds} ms at reference volume.");
    }

    public function test_http_probe_records_passing_immutable_percentile_evidence(): void
    {
        config()->set('operations.performance.allowed_hosts', ['devolution-mis.test']);
        Process::preventStrayProcesses();
        Process::fake(['*' => Process::result($this->apacheBenchOutput())]);

        $this->assertSame(0, Artisan::call('operations:performance-probe', ['--base-url' => 'https://devolution-mis.test', '--path' => '/up', '--requests' => 100, '--concurrency' => 10]));

        $run = PerformanceTestRun::query()->sole();
        $this->assertSame('pass', $run->outcome);
        $this->assertSame(100, $run->request_count);
        $this->assertSame(0, $run->failed_requests);
        $this->assertSame('42.500', $run->requests_per_second);
        $this->assertSame('450.000', $run->p95_latency_ms);
        $this->assertSame(64, strlen($run->output_checksum));
        $this->assertSame(64, strlen($run->evidence_checksum));
        Process::assertRan(fn ($process): bool => $process->command === ['/usr/sbin/ab', '-q', '-l', '-n', '100', '-c', '10', 'https://devolution-mis.test/up']);

        $this->expectException(QueryException::class);
        $run->update(['outcome' => 'fail']);
    }

    public function test_http_probe_rejects_unapproved_targets_without_execution_or_evidence(): void
    {
        config()->set('operations.performance.allowed_hosts', ['devolution-mis.test']);
        Process::preventStrayProcesses();
        Process::fake();

        $this->assertSame(2, Artisan::call('operations:performance-probe', ['--base-url' => 'https://example.org', '--path' => '/up']));
        $this->assertStringContainsString('The target must be an approved same-environment HTTPS host.', Artisan::output());
        $this->assertSame(2, Artisan::call('operations:performance-probe', ['--base-url' => 'https://devolution-mis.test', '--path' => '/login']));
        $this->assertStringContainsString('The requested route is not approved for performance probing.', Artisan::output());

        Process::assertNothingRan();
        $this->assertDatabaseCount('performance_test_runs', 0);
    }

    private function apacheBenchOutput(): string
    {
        return <<<'OUTPUT'
Complete requests:      100
Failed requests:        0
Requests per second:    42.50 [#/sec] (mean)
Time per request:       235.294 [ms] (mean)
Percentage of the requests served within a certain time (ms)
  50%    200
  95%    450
  99%    500
OUTPUT;
    }

    /** @param list<County> $counties */
    private function insertProjects(array $counties, string $sectorId, string $creatorId, int $projectCount, string $titlePrefix = 'Reference volume project'): void
    {
        $createdAt = now();
        foreach (array_chunk(range(1, $projectCount), 200) as $sequenceChunk) {
            $projects = [];
            $projectCounties = [];
            foreach ($sequenceChunk as $sequence) {
                $county = $counties[($sequence - 1) % count($counties)];
                $projectId = (string) Str::uuid7();
                $projects[] = ['id' => $projectId, 'code' => 'PERF-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT), 'title' => "{$titlePrefix} {$sequence}", 'description' => 'Synthetic non-production record for repeatable query and latency assurance.', 'sector_id' => $sectorId, 'lead_county_id' => $county->id, 'lifecycle_stage' => 'execution', 'status' => 'active', 'planned_start_date' => '2025-07-01', 'planned_end_date' => '2027-06-30', 'approved_budget' => '10000000.00', 'committed_amount' => '7500000.00', 'actual_expenditure' => '5000000.00', 'currency' => 'KES', 'physical_progress' => '50.00', 'created_by' => $creatorId, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $projectCounties[] = ['devolution_project_id' => $projectId, 'county_id' => $county->id, 'is_lead' => true, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('devolution_projects')->insert($projects);
            DB::table('devolution_project_county')->insert($projectCounties);
        }
    }

    /** @param list<County> $counties */
    private function insertRepositoryVolume(array $counties, string $uploaderId, int $documentCount): void
    {
        $createdAt = now();
        foreach (array_chunk(range(1, $documentCount), 200) as $sequenceChunk) {
            $documents = [];
            $versions = [];
            $extractions = [];
            foreach ($sequenceChunk as $sequence) {
                $county = $counties[($sequence - 1) % count($counties)];
                $documentId = (string) Str::uuid7();
                $versionId = (string) Str::uuid7();
                $checksum = hash('sha256', "repository-benchmark-{$sequence}");
                $documents[] = ['id' => $documentId, 'county_id' => $county->id, 'category' => 'public_participation', 'source_type' => $sequence % 2 === 0 ? 'scanned' : 'digital', 'title' => "Repository assurance record {$sequence}", 'path' => "performance/repository/{$documentId}.pdf", 'original_name' => "repository-assurance-{$sequence}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'content_checksum' => $checksum, 'scan_status' => 'clean', 'ocr_status' => 'completed', 'security_classification' => 'official', 'record_status' => 'active', 'description' => 'Synthetic non-production repository performance fixture.', 'document_date' => '2026-01-01', 'version' => 1, 'tags' => json_encode(['performance-assurance'], JSON_THROW_ON_ERROR), 'verification_status' => 'verified', 'uploaded_by' => $uploaderId, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $versions[] = ['id' => $versionId, 'assessment_document_id' => $documentId, 'version_number' => 1, 'storage_disk' => 'local', 'path' => "performance/repository/{$documentId}.pdf", 'original_name' => "repository-assurance-{$sequence}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'content_checksum' => $checksum, 'scan_status' => 'clean', 'scanned_at' => $createdAt, 'ocr_status' => 'completed', 'change_summary' => 'Initial representative-volume assurance version.', 'uploaded_by' => $uploaderId, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $extractedText = "repository benchmark county evidence record {$sequence}";
                $extractions[] = ['id' => (string) Str::uuid7(), 'document_version_id' => $versionId, 'status' => 'completed', 'engine' => $sequence % 2 === 0 ? 'tesseract-ocr' : 'native-text', 'language' => 'eng', 'extracted_text' => $extractedText, 'text_checksum_sha256' => hash('sha256', $extractedText), 'character_count' => strlen($extractedText), 'page_count' => 1, 'attempt_count' => 1, 'started_at' => $createdAt, 'completed_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('assessment_documents')->insert($documents);
            DB::table('document_versions')->insert($versions);
            DB::table('document_extractions')->insert($extractions);
        }

        DB::statement('UPDATE assessment_documents SET current_version_id = document_versions.id FROM document_versions WHERE document_versions.assessment_document_id = assessment_documents.id AND document_versions.version_number = 1 AND assessment_documents.current_version_id IS NULL');
    }

    /** @return list<string> */
    private function insertIdentityLifecycleVolume(string $requesterId, string $deciderId, string $roleId, int $eventCount): array
    {
        $createdAt = now();
        $password = User::query()->whereKey($requesterId)->value('password');
        $targetUserIds = [];

        $this->assertIsString($password);

        foreach (array_chunk(range(1, $eventCount), 100) as $sequenceChunk) {
            $users = [];
            $roleAssignments = [];
            $requests = [];
            foreach ($sequenceChunk as $sequence) {
                $userId = (string) Str::uuid7();
                $requestId = (string) Str::uuid7();
                $sourceEventId = 'PERF-JML-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                $targetUserIds[] = $userId;
                $users[] = ['id' => $userId, 'name' => "Reference lifecycle user {$sequence}", 'email' => "performance.lifecycle.{$sequence}@idmis.test", 'email_verified_at' => $createdAt, 'password' => $password, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $roleAssignments[] = ['role_id' => $roleId, 'model_type' => (new User)->getMorphClass(), 'model_uuid' => $userId];
                $sourceChecksum = hash('sha256', "{$sourceEventId}:{$userId}");
                $requests[] = ['id' => $requestId, 'source_system' => 'IPPD-HRIS', 'source_event_id' => $sourceEventId, 'source_evidence_reference' => "DMS-PERF-JML-{$sequence}", 'source_checksum' => $sourceChecksum, 'event_type' => 'leaver', 'user_id' => $userId, 'effective_at' => $createdAt->copy()->subMinute(), 'current_access_snapshot' => json_encode(['role' => 'assessor', 'home_county_id' => null, 'assigned_county_ids' => [], 'delegated_access_ids' => [], 'access_revoked_at' => null], JSON_THROW_ON_ERROR), 'proposed_assigned_county_ids' => json_encode([], JSON_THROW_ON_ERROR), 'business_reason' => 'Synthetic non-production authoritative separation event for representative-volume assurance.', 'status' => 'approved', 'requested_by' => $requesterId, 'decided_by' => $deciderId, 'decision_rationale' => 'The synthetic reference event is independently approved for bounded lifecycle-runner assurance.', 'decided_at' => $createdAt, 'evidence_checksum' => hash('sha256', "approved:{$requestId}:{$sourceChecksum}"), 'application_attempts' => 0, 'sessions_revoked' => 0, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('users')->insert($users);
            DB::table('model_has_roles')->insert($roleAssignments);
            DB::table('identity_lifecycle_requests')->insert($requests);
        }

        return $targetUserIds;
    }

    /** @param list<County> $counties */
    private function insertInnovationReplications(array $counties, string $creatorId, string $referenceDataReleaseId, int $replicationCount): void
    {
        $createdAt = now();
        foreach (array_chunk(range(1, $replicationCount), 200) as $sequenceChunk) {
            $innovations = [];
            $replications = [];
            foreach ($sequenceChunk as $sequence) {
                $targetCounty = $counties[($sequence - 1) % count($counties)];
                $sourceCounty = $counties[$sequence % count($counties)];
                $innovationId = (string) Str::uuid7();
                $innovations[] = ['id' => $innovationId, 'county_id' => $sourceCounty->id, 'reference_data_release_id' => $referenceDataReleaseId, 'submitted_by' => $creatorId, 'reference' => 'PERF-INN-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT), 'title' => "Reference innovation {$sequence}", 'problem_statement' => 'Synthetic non-production record for repeatable portfolio-volume assurance.', 'proposed_solution' => 'A governed county service-delivery operating model.', 'expected_impact' => 'Improved completeness and timeliness of county reporting.', 'maturity_level' => 'proven', 'stage' => 'scale', 'status' => 'scaling', 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $replications[] = ['id' => (string) Str::uuid7(), 'devolution_innovation_id' => $innovationId, 'source_county_id' => $sourceCounty->id, 'target_county_id' => $targetCounty->id, 'reference_data_release_id' => $referenceDataReleaseId, 'accountable_user_id' => $creatorId, 'created_by' => $creatorId, 'reference' => 'PERF-REP-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT), 'adaptation_plan' => 'Synthetic non-production adaptation plan for representative-volume assurance.', 'success_measure' => 'Percentage of complete county submissions', 'baseline_value' => '40.0000', 'target_value' => '85.0000', 'starts_on' => '2026-01-01', 'target_completion_on' => '2026-12-31', 'status' => $sequence % 4 === 0 ? 'piloting' : 'planned', 'verification_decision' => 'pending', 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('devolution_innovations')->insert($innovations);
            DB::table('innovation_replications')->insert($replications);
        }
    }

    /** @param list<County> $counties
     * @param  list<AssessmentCycle>  $cycles
     */
    private function insertAssessmentPublications(array $counties, array $cycles, string $publisherId): void
    {
        $createdAt = now();
        foreach ($cycles as $cycleIndex => $cycle) {
            $assessments = [];
            $publications = [];
            foreach ($counties as $countyIndex => $county) {
                $assessmentId = (string) Str::uuid7();
                $score = 55 + (($countyIndex + $cycleIndex) % 41);
                $assessments[] = ['id' => $assessmentId, 'county_id' => $county->id, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id, 'cycle' => $cycle->code, 'status' => 'published', 'score' => $score, 'completeness_percentage' => '100.00', 'assessed_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                $publications[] = ['id' => (string) Str::uuid7(), 'assessment_id' => $assessmentId, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id, 'county_id' => $county->id, 'score' => $score, 'performance_band' => $score >= 80 ? 'Exceeds standard' : 'Meets standard', 'function_profile' => json_encode([['code' => 'PFM', 'name' => 'Public Financial Management', 'score' => $score], ['code' => 'HRM', 'name' => 'Human Resource Management', 'score' => max(0, $score - 3)]], JSON_THROW_ON_ERROR), 'calculation_snapshot' => json_encode(['formula' => 'representative-volume-fixture-v1'], JSON_THROW_ON_ERROR), 'checksum' => hash('sha256', "{$cycle->id}:{$county->id}:{$score}"), 'published_by' => $publisherId, 'published_at' => $createdAt->copy()->subYears(count($cycles) - $cycleIndex - 1), 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('assessments')->insert($assessments);
            DB::table('assessment_result_publications')->insert($publications);
        }
    }

    /** @param list<County> $counties */
    private function insertLearningAnalyticsVolume(array $counties, User $owner): void
    {
        $createdAt = now();
        $courses = [];
        foreach (range(1, 20) as $sequence) {
            $courses[] = ['id' => (string) Str::uuid7(), 'owner_id' => $owner->id, 'created_by' => $owner->id, 'code' => 'PERF-LRN-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), 'slug' => 'performance-learning-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), 'title' => "Reference learning course {$sequence}", 'summary' => 'Synthetic non-production learning analytics fixture.', 'description' => 'Representative-volume course used only inside an isolated test transaction.', 'category' => 'County capacity building', 'level' => 'foundation', 'delivery_mode' => 'self_paced', 'language' => ReferenceCatalogue::defaultLanguage(), 'estimated_minutes' => 60, 'passing_score' => '70.00', 'maximum_attempts' => 3, 'status' => 'published', 'published_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
        }
        DB::table('learning_courses')->insert($courses);

        $learners = [];
        foreach ($counties as $countyIndex => $county) {
            foreach (range(1, 10) as $learnerSequence) {
                $sequence = ($countyIndex * 10) + $learnerSequence;
                $learners[] = ['id' => (string) Str::uuid7(), 'county_id' => $county->id, 'name' => "Reference learner {$sequence}", 'email' => "performance.learner.{$sequence}@idmis.test", 'email_verified_at' => $createdAt, 'password' => $owner->password, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
        }
        DB::table('users')->insert($learners);

        foreach (array_chunk($learners, 100) as $learnerChunk) {
            $enrollments = [];
            foreach ($learnerChunk as $learnerIndex => $learner) {
                foreach ($courses as $courseIndex => $course) {
                    $completed = ($learnerIndex + $courseIndex) % 2 === 0;
                    $enrollments[] = ['id' => (string) Str::uuid7(), 'learning_course_id' => $course['id'], 'user_id' => $learner['id'], 'county_id' => $learner['county_id'], 'status' => $completed ? 'completed' : 'in_progress', 'progress_percentage' => $completed ? '100.00' : '50.00', 'best_score' => $completed ? '82.00' : '60.00', 'enrolled_at' => $createdAt, 'started_at' => $createdAt, 'last_activity_at' => $createdAt, 'completed_at' => $completed ? $createdAt : null, 'enrolled_by' => $owner->id, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                }
            }
            DB::table('learning_enrollments')->insert($enrollments);
        }
    }

    /** @param list<County> $counties */
    private function insertKnowledgeCommunityVolume(array $counties, string $authorId): void
    {
        $createdAt = now();
        foreach ($counties as $countyIndex => $county) {
            $discussions = [];
            $posts = [];
            $subscriptions = [];
            foreach (range(1, 20) as $discussionSequence) {
                $sequence = ($countyIndex * 20) + $discussionSequence;
                $discussionId = (string) Str::uuid7();
                $discussions[] = ['id' => $discussionId, 'county_id' => $county->id, 'created_by' => $authorId, 'title' => "Reference county practice {$sequence}", 'prompt' => 'Synthetic non-production community-health fixture.', 'status' => 'open', 'visibility' => 'county', 'last_posted_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                foreach (range(1, 4) as $postSequence) {
                    $posts[] = ['id' => (string) Str::uuid7(), 'knowledge_discussion_id' => $discussionId, 'author_id' => $authorId, 'body' => "Reference visible contribution {$postSequence}", 'is_moderated' => false, 'moderation_status' => 'visible', 'posted_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                }
                $subscriptions[] = ['id' => (string) Str::uuid7(), 'knowledge_discussion_id' => $discussionId, 'user_id' => $authorId, 'delivery_frequency' => 'instant', 'subscribed_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
            }
            DB::table('knowledge_discussions')->insert($discussions);
            DB::table('knowledge_posts')->insert($posts);
            DB::table('knowledge_discussion_subscriptions')->insert($subscriptions);
        }
    }

    /** @param callable(): TestResponse<SymfonyResponse> $request
     * @return array{TestResponse<SymfonyResponse>, int, float}
     */
    private function measure(callable $request): array
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $startedAt = hrtime(true);
        $response = $request();
        $milliseconds = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $queryCount = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return [$response, $queryCount, $milliseconds];
    }

    /** @param callable(): int $operation
     * @return array{int, int, float}
     */
    private function measureOperation(callable $operation): array
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $startedAt = hrtime(true);
        $result = $operation();
        $milliseconds = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $queryCount = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return [$result, $queryCount, $milliseconds];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return array{json:string, executionMilliseconds:float}
     */
    private function explainAnalyze(string $sql, array $bindings): array
    {
        $result = DB::selectOne("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) {$sql}", $bindings);
        $this->assertNotNull($result);
        $json = array_values((array) $result)[0] ?? null;
        $this->assertIsString($json);
        $plan = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($plan);
        $executionMilliseconds = $plan[0]['Execution Time'] ?? null;
        $this->assertIsFloat($executionMilliseconds);

        return ['json' => $json, 'executionMilliseconds' => $executionMilliseconds];
    }
}
