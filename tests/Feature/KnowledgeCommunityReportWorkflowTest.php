<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Database\Seeders\KnowledgeWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeCommunityReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_creation_is_scoped_unique_audited_and_sla_controlled(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $decisionMaker = User::factory()->devolutionAdmin()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $reporter = User::factory()->countyOfficial($county)->create();
        $outsider = User::factory()->countyOfficial($otherCounty)->create();
        $this->seed(KnowledgeWorkflowSeeder::class);
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $author->id]);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id]);
        $payload = ['category' => 'misinformation', 'severity' => 'high', 'description' => 'The contribution makes a material claim without the evidence required for safe cross-county reuse.'];

        $this->actingAs($reporter)->post(route('knowledge.posts.reports.store', [$post]), $payload)->assertRedirect();
        $report = KnowledgeCommunityReport::query()->with('workflowInstance')->sole();
        $this->assertTrue(Str::isUuid($report->id));
        $this->assertSame('reported', $report->status);
        $this->assertSame($county->id, $report->county_id);
        $this->assertNotNull($report->workflow_instance_id);
        $this->assertSame(24, (int) $report->workflowInstance->started_at->diffInHours($report->workflowInstance->due_at));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $report->id, 'action' => 'knowledge.community_report.created']);
        Notification::assertSentTo($reporter, ProgrammeAlert::class);

        $this->actingAs($reporter)->post(route('knowledge.posts.reports.store', [$post]), $payload)->assertSessionHasErrors('description');
        $this->actingAs($author)->post(route('knowledge.posts.reports.store', [$post]), $payload)->assertForbidden();
        $this->actingAs($outsider)->post(route('knowledge.posts.reports.store', [$post]), $payload)->assertForbidden();
        $this->assertSame(1, KnowledgeCommunityReport::query()->count());
        $this->assertNotNull($decisionMaker->id);
    }

    public function test_independent_triage_and_decision_update_the_post_and_notify_the_reporter(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $decisionMaker = User::factory()->devolutionAdmin()->create();
        $triager = User::factory()->platformAdmin()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $reporter = User::factory()->countyOfficial($county)->create();
        $this->seed(KnowledgeWorkflowSeeder::class);
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $author->id]);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id]);
        $this->actingAs($reporter)->post(route('knowledge.posts.reports.store', [$post]), ['category' => 'privacy', 'severity' => 'critical', 'description' => 'The contribution appears to disclose restricted personal information requiring immediate controlled review.'])->assertRedirect();
        $report = KnowledgeCommunityReport::query()->sole();

        $this->actingAs($reporter)->patch(route('knowledge.community-reports.transition', [$report]), ['transition' => 'triage', 'rationale' => 'Attempted reporter self-triage is not permitted.'])->assertForbidden();
        $this->actingAs($triager)->patch(route('knowledge.community-reports.transition', [$report]), ['transition' => 'triage', 'rationale' => 'Initial evidence indicates a credible privacy risk requiring independent decision.'])->assertRedirect();
        $this->assertSame('investigating', $report->refresh()->status);
        $this->assertSame($triager->id, $report->triaged_by);
        $workflow = $report->workflowInstance()->firstOrFail();
        $this->assertSame(72, (int) $workflow->state_entered_at->diffInHours($workflow->due_at));

        $decision = ['transition' => 'resolve', 'rationale' => 'Independent review confirms the disclosure and requires immediate suppression.', 'post_action' => 'hide', 'resolution' => 'The contribution is hidden, the original is retained for audit, and the privacy owner must complete follow-up.'];
        $this->actingAs($triager)->patch(route('knowledge.community-reports.transition', [$report]), $decision)->assertForbidden();
        $this->actingAs($decisionMaker)->patch(route('knowledge.community-reports.transition', [$report]), [...$decision, 'resolution' => 'short'])->assertSessionHasErrors('resolution');
        $this->actingAs($decisionMaker)->patch(route('knowledge.community-reports.transition', [$report]), $decision)->assertRedirect();

        $report->refresh();
        $post->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame('hide', $report->post_action);
        $this->assertSame($decisionMaker->id, $report->decided_by);
        $this->assertSame('hidden', $post->moderation_status);
        $this->assertStringContainsString($report->reference, (string) $post->moderation_reason);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $report->id, 'action' => 'knowledge.community_report.transitioned']);
        Notification::assertSentToTimes($reporter, ProgrammeAlert::class, 3);
    }

    public function test_queue_filters_county_scope_pagination_and_all_export_formats(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $nationalManager = User::factory()->devolutionAdmin()->create();
        $countyCurator = User::factory()->topManagement()->create();
        $otherCurator = User::factory()->topManagement()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $reporter = User::factory()->countyOfficial($county)->create();
        $countyCurator->assignedCounties()->attach($county);
        $otherCurator->assignedCounties()->attach($otherCounty);
        $this->seed(KnowledgeWorkflowSeeder::class);
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $author->id, 'title' => 'County safeguards exchange']);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id]);
        $this->actingAs($reporter)->post(route('knowledge.posts.reports.store', [$post]), ['category' => 'security', 'severity' => 'high', 'description' => 'The contribution includes operational details that require a controlled security review before wider reuse.'])->assertRedirect();
        $report = KnowledgeCommunityReport::query()->sole();

        $this->actingAs($countyCurator)->get(route('knowledge.index', ['report_search' => $report->reference, 'report_status' => 'reported']))->assertOk()->assertInertia(fn ($page) => $page->where('reports.total', 1)->where('reports.data.0.county.id', $county->id));
        $this->actingAs($otherCurator)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page->where('reports.total', 0));
        $this->actingAs($reporter)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page->where('reports.total', 1));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($nationalManager)->get(route('workspace.export', ['knowledge-moderation', $format, 'status' => 'reported']))->assertOk()->assertDownload();
        }
    }

    public function test_legacy_report_without_a_workflow_remains_visible_with_explicit_missing_due_date(): void
    {
        $manager = User::factory()->devolutionAdmin()->create();
        $author = User::factory()->create();
        $discussion = KnowledgeDiscussion::factory()->create(['created_by' => $author->id]);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id]);
        $report = KnowledgeCommunityReport::factory()->create([
            'knowledge_post_id' => $post->id,
            'reported_by' => $manager->id,
            'workflow_instance_id' => null,
        ]);

        $this->actingAs($manager)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('reports.total', 1)
            ->where('reports.data.0.id', $report->id)
            ->where('reports.data.0.dueAt', null));
    }
}
