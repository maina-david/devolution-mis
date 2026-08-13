<?php

namespace Tests\Feature;

use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\User;
use App\Services\IgrDependencyAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IgrDependencyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_critical_paths_blockers_and_bottlenecks(): void
    {
        $creator = User::factory()->create();
        $root = IgrResolution::factory()->create(['status' => 'closed']);
        $middle = IgrResolution::factory()->create(['igr_forum_id' => $root->igr_forum_id]);
        $leaf = IgrResolution::factory()->create(['igr_forum_id' => $root->igr_forum_id]);
        $branch = IgrResolution::factory()->create(['igr_forum_id' => $root->igr_forum_id]);

        IgrResolutionDependency::factory()->create(['dependent_resolution_id' => $middle->id, 'prerequisite_resolution_id' => $root->id, 'dependency_type' => 'blocks', 'created_by' => $creator->id]);
        IgrResolutionDependency::factory()->create(['dependent_resolution_id' => $leaf->id, 'prerequisite_resolution_id' => $middle->id, 'dependency_type' => 'blocks', 'created_by' => $creator->id]);
        IgrResolutionDependency::factory()->create(['dependent_resolution_id' => $branch->id, 'prerequisite_resolution_id' => $middle->id, 'dependency_type' => 'informs', 'created_by' => $creator->id]);

        $resolutions = IgrResolution::query()->with('dependencies')->whereKey([$root->id, $middle->id, $leaf->id, $branch->id])->get();
        $report = app(IgrDependencyAnalytics::class)->report($resolutions);

        $this->assertSame(3, $report['summary']['totalLinks']);
        $this->assertSame(2, $report['summary']['blockingLinks']);
        $this->assertSame(1, $report['summary']['unresolvedBlockingLinks']);
        $this->assertSame(1, $report['summary']['blockedResolutions']);
        $this->assertSame(2, $report['summary']['longestPathDepth']);
        $this->assertSame([$root->id, $middle->id, $leaf->id], array_column($report['criticalPaths'][0]['nodes'], 'id'));
        $this->assertSame($middle->id, $report['bottlenecks'][0]['id']);
        $this->assertSame(2, $report['bottlenecks'][0]['dependentCount']);
    }

    public function test_it_excludes_links_to_resolutions_outside_the_visible_scope(): void
    {
        $creator = User::factory()->create();
        $hidden = IgrResolution::factory()->create();
        $visible = IgrResolution::factory()->create(['igr_forum_id' => $hidden->igr_forum_id]);
        IgrResolutionDependency::factory()->create(['dependent_resolution_id' => $visible->id, 'prerequisite_resolution_id' => $hidden->id, 'dependency_type' => 'blocks', 'created_by' => $creator->id]);

        $report = app(IgrDependencyAnalytics::class)->report(IgrResolution::query()->with('dependencies')->whereKey($visible->id)->get());

        $this->assertSame(0, $report['summary']['totalLinks']);
        $this->assertSame([], $report['criticalPaths']);
        $this->assertSame([], $report['bottlenecks']);
    }
}
