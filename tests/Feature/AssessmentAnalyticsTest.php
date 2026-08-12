<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentResultPublication;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_portfolio_and_national_roles_receive_only_their_authorized_publications(): void
    {
        $cycle = AssessmentCycle::factory()->create(['code' => 'ACPA-2025', 'name' => '2025 ACPA']);
        $countyA = County::factory()->create(['code' => 1, 'name' => 'Mombasa']);
        $countyB = County::factory()->create(['code' => 2, 'name' => 'Kwale']);
        $countyC = County::factory()->create(['code' => 3, 'name' => 'Kilifi']);
        $this->publication($countyA, $cycle, 81, 'F01');
        $this->publication($countyB, $cycle, 72, 'F01');
        $this->publication($countyC, $cycle, 63, 'F01');

        $countyAdmin = User::factory()->countyAdmin($countyA)->create();
        $assessor = User::factory()->assessor()->create();
        $countyA->assignedUsers()->attach($assessor);
        $countyB->assignedUsers()->attach($assessor);
        $nationalAdmin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($countyAdmin)->get(route('assessments.analytics.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('assessments/analytics')
            ->where('report.summary.counties', 1)
            ->where('report.summary.publications', 1)
            ->has('report.options.counties', 1)
            ->has('report.rankings.rows', 1)
            ->where('report.counties.0.county.name', 'Mombasa'));

        $this->actingAs($assessor)->get(route('assessments.analytics.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.counties', 2)
            ->has('report.options.counties', 2)
            ->has('report.rankings.rows', 2));

        $this->actingAs($nationalAdmin)->get(route('assessments.analytics.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.counties', 3)
            ->has('report.options.counties', 3)
            ->has('report.rankings.rows', 3));

        $this->actingAs($countyAdmin)->get(route('assessments.analytics.index', ['county_id' => $countyB->id]))->assertForbidden();
    }

    public function test_cycles_dates_counties_and_function_profiles_are_filterable_and_reproducible(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $olderCycle = AssessmentCycle::factory()->create(['code' => 'ACPA-2024', 'name' => '2024 ACPA', 'period_start' => '2024-01-01', 'period_end' => '2024-12-31']);
        $newerCycle = AssessmentCycle::factory()->create(['code' => 'ACPA-2025', 'name' => '2025 ACPA', 'period_start' => '2025-01-01', 'period_end' => '2025-12-31']);
        $this->publication($county, $olderCycle, 64, 'F01', '2025-01-20');
        $publication = $this->publication($county, $newerCycle, 84, 'F02', '2026-01-20');

        $this->actingAs($admin)->get(route('assessments.analytics.index', ['from' => '2026-01-01',
            'to' => '2026-12-31',
            'cycle_id' => $newerCycle->id,
            'county_id' => $county->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.cycle_id', $newerCycle->id)
            ->where('report.summary.publications', 1)
            ->where('report.summary.averageScore', 84)
            ->where('report.cycles.0.code', 'ACPA-2025')
            ->where('report.functions.rows.0.code', 'F02')
            ->where('report.counties.0.results.0.checksum', $publication->checksum));
    }

    public function test_authorized_filtered_exports_are_available_in_all_formats_and_audited(): void
    {
        $county = County::factory()->create();
        $cycle = AssessmentCycle::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $this->publication($county, $cycle, 77, 'F01');
        $hiddenCounty = County::factory()->create(['name' => 'Hidden export county']);
        $this->publication($hiddenCounty, $cycle, 91, 'F01');

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($admin)->get(route('assessments.analytics.export', ['format' => $format,
                'cycle_id' => $cycle->id,
            ]));
            $response->assertOk()->assertDownload();
            if ($format === 'csv') {
                $content = $response->streamedContent();
                $this->assertStringContainsString($county->name, $content);
                $this->assertStringNotContainsString($hiddenCounty->name, $content);
            }
        }

        $this->assertDatabaseCount('audit_events', 4);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.analytics_exported', 'actor_id' => $admin->id]);
    }

    public function test_assessment_detail_does_not_expose_out_of_scope_county_rankings(): void
    {
        $cycle = AssessmentCycle::factory()->create();
        $visibleCounty = County::factory()->create();
        $hiddenCounty = County::factory()->create();
        $visible = $this->publication($visibleCounty, $cycle, 70, 'F01');
        $this->publication($hiddenCounty, $cycle, 90, 'F01');
        $countyAdmin = User::factory()->countyAdmin($visibleCounty)->create();

        $this->actingAs($countyAdmin)->get(route('assessments.show', [$visible->assessment_id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assessment.rankings', 1)
            ->where('assessment.rankings.0.countyId', $visibleCounty->id)
            ->where('assessment.rankings.0.countyIdentity.kind', 'county')
            ->where('assessment.rankings.0.countyIdentity.logoUrl', $visibleCounty->logo_path)
            ->where('assessment.rankings.0.rank', 1));
    }

    public function test_function_and_ranking_tables_are_independently_server_paginated(): void
    {
        $cycle = AssessmentCycle::factory()->create();
        $firstCounty = County::factory()->create(['code' => 1]);
        $assessment = Assessment::factory()->create([
            'county_id' => $firstCounty->id,
            'assessment_cycle_id' => $cycle->id,
            'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id,
            'cycle' => $cycle->code,
        ]);
        AssessmentResultPublication::factory()->create([
            'assessment_id' => $assessment->id,
            'assessment_cycle_id' => $cycle->id,
            'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id,
            'county_id' => $firstCounty->id,
            'function_profile' => collect(range(1, 12))->map(fn (int $number): array => ['code' => sprintf('F%02d', $number), 'name' => "Function {$number}", 'score' => 70 + $number])->all(),
        ]);
        foreach (range(2, 12) as $code) {
            $county = County::factory()->create(['code' => $code]);
            $this->publication($county, $cycle, 60 + $code, 'F01');
        }
        $admin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($admin)->get(route('assessments.analytics.index', ['cycle_id' => $cycle->id,
            'function_page' => 2,
            'ranking_page' => 2,
            'per_page' => 10,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('report.functions.rows', 2)
            ->where('report.functions.pagination.total', 12)
            ->where('report.functions.pagination.currentPage', 2)
            ->where('report.functions.pagination.pageName', 'function_page')
            ->has('report.rankings.rows', 2)
            ->where('report.rankings.pagination.total', 12)
            ->where('report.rankings.pagination.currentPage', 2)
            ->where('report.rankings.pagination.pageName', 'ranking_page'));
    }

    private function publication(County $county, AssessmentCycle $cycle, float $score, string $functionCode, ?string $publishedAt = null): AssessmentResultPublication
    {
        $assessment = Assessment::factory()->create([
            'county_id' => $county->id,
            'assessment_cycle_id' => $cycle->id,
            'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id,
            'cycle' => $cycle->code,
            'score' => $score,
        ]);

        return AssessmentResultPublication::factory()->create([
            'assessment_id' => $assessment->id,
            'assessment_cycle_id' => $cycle->id,
            'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id,
            'county_id' => $county->id,
            'score' => $score,
            'function_profile' => [['code' => $functionCode, 'name' => "Function {$functionCode}", 'score' => $score]],
            'published_at' => $publishedAt ?? now(),
        ]);
    }
}
