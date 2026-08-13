<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgePost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KnowledgeCommunityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_health_is_calculated_inside_authorized_county_scope_without_hidden_content(): void
    {
        $home = County::factory()->create(['code' => 14, 'name' => 'Embu']);
        $other = County::factory()->create(['code' => 22, 'name' => 'Kiambu']);
        $nationalAdmin = User::factory()->devolutionAdmin()->create();
        $countyAdmin = User::factory()->countyAdmin($home)->create();
        $ordinaryParticipant = User::factory()->countyOfficial($home)->create();
        $homeDiscussion = KnowledgeDiscussion::factory()->create(['county_id' => $home->id, 'title' => 'Embu planning practice', 'last_posted_at' => '2026-07-20']);
        $otherDiscussion = KnowledgeDiscussion::factory()->create(['county_id' => $other->id, 'title' => 'Kiambu planning practice', 'last_posted_at' => '2026-08-02']);
        $nationalDiscussion = KnowledgeDiscussion::factory()->create(['county_id' => null, 'title' => 'National peer exchange', 'last_posted_at' => '2026-07-25']);
        $homeAuthor = User::factory()->countyOfficial($home)->create();

        $homePost = KnowledgePost::factory()->create(['knowledge_discussion_id' => $homeDiscussion->id, 'author_id' => $homeAuthor->id, 'body' => 'Visible county practice', 'posted_at' => '2026-07-12']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $homeDiscussion->id, 'author_id' => $countyAdmin->id, 'body' => 'Private moderation evidence must not leak', 'moderation_status' => 'hidden', 'is_moderated' => true, 'moderated_by' => $nationalAdmin->id, 'moderated_at' => '2026-07-13', 'moderation_reason' => 'Contains personal data requiring protected handling.', 'posted_at' => '2026-07-13']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $otherDiscussion->id, 'body' => 'Visible other county practice', 'posted_at' => '2026-08-02']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $nationalDiscussion->id, 'body' => 'Visible national contribution', 'posted_at' => '2026-07-25']);
        KnowledgeDiscussionSubscription::factory()->create(['knowledge_discussion_id' => $homeDiscussion->id, 'user_id' => $homeAuthor->id, 'subscribed_at' => '2026-07-10']);
        KnowledgeCommunityReport::factory()->create(['knowledge_post_id' => $homePost->id, 'county_id' => $home->id, 'reported_by' => $countyAdmin->id, 'status' => 'resolved', 'resolution' => 'The visible contribution was verified and retained.', 'post_action' => 'keep_visible', 'decided_by' => $nationalAdmin->id, 'decided_at' => '2026-07-15', 'created_at' => '2026-07-14']);

        $this->actingAs($countyAdmin)->get(route('knowledge.community-analytics.index'))
            ->assertOk()
            ->assertDontSee('Private moderation evidence must not leak')
            ->assertInertia(fn (Assert $page) => $page
                ->component('knowledge/community-analytics')
                ->where('report.summary.discussions', 2)
                ->where('report.summary.contributions', 2)
                ->where('report.summary.subscriptions', 1)
                ->where('report.summary.reports', 1)
                ->where('report.summary.resolutionRate', 100)
                ->has('report.counties.rows', 1)
                ->where('report.counties.rows.0.county.name', 'Embu')
                ->has('report.discussions.rows', 2)
            );

        $this->actingAs($nationalAdmin)->get(route('knowledge.community-analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.discussions', 3)
                ->where('report.summary.contributions', 3)
                ->has('report.counties.rows', 2)
            );

        $this->actingAs($countyAdmin)->get(route('knowledge.community-analytics.index', ['county_id' => $other->id]))->assertForbidden();
        $this->actingAs($ordinaryParticipant)->get(route('knowledge.community-analytics.index'))->assertForbidden();
    }

    public function test_filters_pagination_exports_and_audit_share_the_governed_analytics_scope(): void
    {
        $county = County::factory()->create(['name' => 'Kisumu']);
        $other = County::factory()->create(['name' => 'Nyeri']);
        $admin = User::factory()->devolutionAdmin()->create();
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'title' => 'County public participation', 'status' => 'open']);
        $otherDiscussion = KnowledgeDiscussion::factory()->create(['county_id' => $other->id, 'title' => 'Unrelated procurement practice', 'status' => 'closed']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'posted_at' => '2026-06-12']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'posted_at' => '2026-08-12']);
        KnowledgePost::factory()->create(['knowledge_discussion_id' => $otherDiscussion->id, 'posted_at' => '2026-06-12']);

        $filters = ['county_id' => $county->id, 'status' => 'open', 'search' => 'participation', 'from' => '2026-06-01', 'to' => '2026-06-30', 'per_page' => 10];
        $this->actingAs($admin)->get(route('knowledge.community-analytics.index', [...$filters]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.county_id', $county->id)
                ->where('report.summary.discussions', 1)
                ->where('report.summary.contributions', 1)
                ->where('report.discussions.pagination.total', 1)
                ->where('report.discussions.rows.0.title', 'County public participation')
                ->where('report.trend.0.period', '2026-06')
                ->where('report.trend.0.contributions', 1)
            );

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($admin)->get(route('knowledge.community-analytics.export', [$format, ...$filters]))->assertOk()->assertDownload();
        }

        $json = $this->actingAs($admin)->get(route('knowledge.community-analytics.export', ['json', ...$filters]));
        $content = $json->streamedContent();
        $this->assertStringContainsString('County public participation', $content);
        $this->assertStringNotContainsString('Unrelated procurement practice', $content);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'action' => 'knowledge.community_analytics.exported']);

        $swahiliCsv = $this->actingAs($admin)
            ->withSession(['locale' => 'sw'])
            ->get(route('knowledge.community-analytics.export', ['csv', ...$filters]));
        $swahiliCsv->assertOk()->assertDownload();
        $this->assertStringContainsString('Kaunti,"Msimbo wa kaunti",Mjadala', $swahiliCsv->streamedContent());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'knowledge.community_analytics.exported',
            'description' => 'Uchanganuzi wa jumuiya ya maarifa umehamishwa kama CSV.',
        ]);
    }

    public function test_community_health_interface_uses_the_active_locale(): void
    {
        $county = County::factory()->create();
        KnowledgeDiscussion::factory()->create(['county_id' => $county->id]);
        $countyAdmin = User::factory()->countyAdmin($county)->create();

        $this->actingAs($countyAdmin)
            ->withSession(['locale' => 'fr'])
            ->get(route('knowledge.community-analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.knowledge.ui.community_health', 'Santé de la communauté')
                ->where('localization.knowledge.ui.visible_contributions', 'Contributions visibles'));
    }

    public function test_dedicated_analytics_permission_enforces_the_complete_role_and_scope_matrix(): void
    {
        $home = County::factory()->create(['name' => 'Bungoma']);
        $other = County::factory()->create(['name' => 'Busia']);
        KnowledgeDiscussion::factory()->create(['county_id' => $home->id, 'title' => 'Bungoma practice exchange']);
        KnowledgeDiscussion::factory()->create(['county_id' => $other->id, 'title' => 'Busia practice exchange']);

        $countyAdmin = User::factory()->countyAdmin($home)->create();
        $this->actingAs($countyAdmin)->get(route('knowledge.community-analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.discussions', 1)
                ->where('report.counties.rows.0.county.name', 'Bungoma')
                ->has('report.counties.rows', 1)
            );

        $assessor = User::factory()->assessor()->create();
        $partner = User::factory()->developmentPartner()->create();
        $topManagement = User::factory()->topManagement()->create();
        foreach ([$assessor, $partner, $topManagement] as $portfolioUser) {
            $portfolioUser->assignedCounties()->sync([$home->id, $other->id]);
        }
        $nationalUsers = [$assessor, $partner, $topManagement, User::factory()->devolutionAdmin()->create(), User::factory()->platformAdmin()->create()];

        foreach ($nationalUsers as $user) {
            $this->actingAs($user)->get(route('knowledge.community-analytics.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('report.summary.discussions', 2)
                    ->has('report.counties.rows', 2)
                );
        }

        $countyOfficial = User::factory()->countyOfficial($home)->create();
        $this->actingAs($countyOfficial)->get(route('knowledge.community-analytics.index'))->assertForbidden();
    }
}
