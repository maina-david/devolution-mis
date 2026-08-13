<?php

namespace Tests\Feature;

use App\Models\CitizenCase;
use App\Models\County;
use App\Services\CitizenIssueAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitizenIssueAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_trends_backlogs_and_resolution_time_for_a_scoped_register(): void
    {
        $county = County::factory()->create();
        CitizenCase::factory()->count(2)->create(['county_id' => $county->id, 'category' => 'complaint', 'channel' => 'web', 'created_at' => now()->subDays(5), 'resolution_due_at' => now()->subDay()]);
        CitizenCase::factory()->create(['county_id' => $county->id, 'category' => 'grievance', 'channel' => 'phone', 'status' => 'resolved', 'created_at' => now()->subDays(4), 'resolved_at' => now()->subDays(2)]);

        $report = app(CitizenIssueAnalytics::class)->report(CitizenCase::query()->where('county_id', $county->id));

        $this->assertSame('complaint', $report['categories'][0]['label']);
        $this->assertSame(2, $report['categories'][0]['total']);
        $this->assertSame(2, $report['overdue']);
        $this->assertSame(48.0, $report['averageResolutionHours']);
        $this->assertSame(3, $report['monthlyTrend'][0]['total']);
        $this->assertSame(1, $report['monthlyTrend'][0]['resolved']);
    }

    public function test_public_report_suppresses_low_frequency_signals(): void
    {
        CitizenCase::factory()->count(2)->create(['category' => 'rare-signal']);
        CitizenCase::factory()->count(3)->create(['category' => 'publishable-signal']);

        $report = app(CitizenIssueAnalytics::class)->report(public: true);

        $this->assertSame([['label' => 'publishable-signal', 'total' => 3]], $report['categories']);
        $this->assertSame(3, $report['minimumPublishedCount']);
    }

    public function test_it_reports_satisfaction_coverage_segments_and_resolution_time_correlation(): void
    {
        $county = County::factory()->create();
        foreach ([
            ['rating' => 5, 'hours' => 12, 'category' => 'complaint', 'channel' => 'web'],
            ['rating' => 4, 'hours' => 24, 'category' => 'complaint', 'channel' => 'web'],
            ['rating' => 3, 'hours' => 36, 'category' => 'complaint', 'channel' => 'phone'],
            ['rating' => 2, 'hours' => 48, 'category' => 'grievance', 'channel' => 'phone'],
            ['rating' => 1, 'hours' => 60, 'category' => 'grievance', 'channel' => 'phone'],
        ] as $sample) {
            CitizenCase::factory()->create([
                'county_id' => $county->id,
                'category' => $sample['category'],
                'channel' => $sample['channel'],
                'status' => 'resolved',
                'created_at' => now()->subHours($sample['hours']),
                'resolved_at' => now(),
                'satisfaction_rating' => $sample['rating'],
                'satisfaction_recorded_at' => now(),
            ]);
        }

        $report = app(CitizenIssueAnalytics::class)->report(CitizenCase::query()->where('county_id', $county->id));

        $this->assertSame(5, $report['satisfaction']['responses']);
        $this->assertSame(100.0, $report['satisfaction']['responseRate']);
        $this->assertSame(3.0, $report['satisfaction']['averageRating']);
        $this->assertCount(5, $report['satisfaction']['distribution']);
        $this->assertSame('complaint', $report['satisfaction']['byCategory'][0]['label']);
        $this->assertSame(4.0, $report['satisfaction']['byCategory'][0]['averageRating']);
        $this->assertSame(5, $report['satisfaction']['resolutionTimeCorrelation']['samples']);
        $this->assertSame(-1.0, $report['satisfaction']['resolutionTimeCorrelation']['coefficient']);
    }

    public function test_public_satisfaction_analytics_suppress_small_cells(): void
    {
        CitizenCase::factory()->count(2)->create([
            'status' => 'resolved',
            'resolved_at' => now(),
            'satisfaction_rating' => 5,
            'satisfaction_recorded_at' => now(),
        ]);

        $report = app(CitizenIssueAnalytics::class)->report(public: true);

        $this->assertNull($report['satisfaction']['responses']);
        $this->assertNull($report['satisfaction']['averageRating']);
        $this->assertSame([], $report['satisfaction']['distribution']);
        $this->assertNull($report['satisfaction']['resolutionTimeCorrelation']['coefficient']);
    }
}
