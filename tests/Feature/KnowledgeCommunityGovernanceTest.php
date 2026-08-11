<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgeItem;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeCommunityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_discussion_following_is_scoped_idempotent_audited_and_recoverable(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $creator = User::factory()->countyOfficial($county)->create();
        $outsider = User::factory()->countyOfficial($otherCounty)->create();

        $this->actingAs($creator)->post(route('knowledge.discussions.store', $creator->currentTeam->slug), [
            'county_id' => $county->id,
            'title' => 'County peer-learning forum',
            'prompt' => 'Which verified practices should counties adapt and measure?',
            'visibility' => 'county',
        ])->assertRedirect();

        $discussion = KnowledgeDiscussion::query()->sole();
        $subscription = KnowledgeDiscussionSubscription::query()->sole();
        $this->assertTrue(Str::isUuid($subscription->id));
        $this->assertSame($creator->id, $subscription->user_id);

        $route = route('knowledge.discussions.subscription', [$creator->currentTeam->slug, $discussion]);
        $this->actingAs($creator)->patch($route, ['subscribed' => true])->assertRedirect();
        $this->assertSame(1, KnowledgeDiscussionSubscription::withTrashed()->count());
        $this->actingAs($creator)->patch($route, ['subscribed' => false])->assertRedirect();
        $this->assertSoftDeleted($subscription);
        $this->actingAs($creator)->patch($route, ['subscribed' => true])->assertRedirect();
        $this->assertNotNull($subscription->fresh());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $discussion->id, 'action' => 'knowledge.discussion.subscribed']);

        $this->actingAs($outsider)->patch(route('knowledge.discussions.subscription', [$outsider->currentTeam->slug, $discussion]), ['subscribed' => true])->assertForbidden();
    }

    public function test_visible_contributions_notify_only_other_active_subscribers(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $subscriber = User::factory()->countyAdmin($county)->create();
        $unsubscribedUser = User::factory()->countyOfficial($county)->create();
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $subscriber->id]);
        KnowledgeDiscussionSubscription::factory()->create(['knowledge_discussion_id' => $discussion->id, 'user_id' => $subscriber->id]);
        KnowledgeDiscussionSubscription::factory()->create(['knowledge_discussion_id' => $discussion->id, 'user_id' => $author->id]);
        KnowledgeDiscussionSubscription::factory()->create(['knowledge_discussion_id' => $discussion->id, 'user_id' => $unsubscribedUser->id])->delete();

        $this->actingAs($author)->post(route('knowledge.posts.store', [$author->currentTeam->slug, $discussion]), [
            'body' => 'A verified peer-learning contribution with a documented county implementation result.',
        ])->assertRedirect();

        $post = KnowledgePost::query()->sole();
        $this->assertSame('visible', $post->moderation_status);
        $this->assertFalse($post->is_moderated);
        Notification::assertSentTo($subscriber, ProgrammeAlert::class, fn (ProgrammeAlert $notification): bool => $notification->category === 'knowledge' && str_contains($notification->message, $discussion->title));
        Notification::assertNotSentTo($author, ProgrammeAlert::class);
        Notification::assertNotSentTo($unsubscribedUser, ProgrammeAlert::class);
    }

    public function test_independent_scoped_moderation_preserves_content_and_hides_it_from_participants(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $curator = User::factory()->topManagement()->create();
        $outsider = User::factory()->topManagement()->create();
        $curator->assignedCounties()->attach($county);
        $outsider->assignedCounties()->attach($otherCounty);
        $item = KnowledgeItem::factory()->create(['county_id' => $county->id, 'author_id' => $author->id, 'status' => 'published']);
        $discussion = KnowledgeDiscussion::factory()->create(['knowledge_item_id' => $item->id, 'county_id' => $county->id, 'created_by' => $author->id]);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id, 'body' => 'The original contribution remains available as immutable moderation context.']);
        $route = route('knowledge.posts.moderate', [$curator->currentTeam->slug, $post]);
        $payload = ['moderation_status' => 'hidden', 'moderation_reason' => 'The contribution contains an unverified assertion requiring evidence before republication.'];

        $this->actingAs($author)->patch(route('knowledge.posts.moderate', [$author->currentTeam->slug, $post]), $payload)->assertForbidden();
        $this->actingAs($outsider)->patch(route('knowledge.posts.moderate', [$outsider->currentTeam->slug, $post]), $payload)->assertForbidden();
        $this->actingAs($curator)->patch($route, ['moderation_status' => 'hidden', 'moderation_reason' => 'Too short'])->assertSessionHasErrors('moderation_reason');
        $this->actingAs($curator)->patch($route, $payload)->assertRedirect();

        $post->refresh();
        $this->assertSame('hidden', $post->moderation_status);
        $this->assertSame('The original contribution remains available as immutable moderation context.', $post->body);
        $this->assertSame($curator->id, $post->moderated_by);
        $this->assertNotNull($post->moderated_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $post->id, 'action' => 'knowledge.post.moderated']);
        $this->actingAs($author)->get(route('knowledge.index', $author->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('items.data.0.discussions.0.posts', []));
        $this->actingAs($curator)->get(route('knowledge.index', $curator->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('items.data.0.discussions.0.posts.0.moderationStatus', 'hidden')->where('items.data.0.discussions.0.posts.0.body', $post->body));

        $this->actingAs($curator)->patch($route, ['moderation_status' => 'visible', 'moderation_reason' => 'The supporting evidence was independently verified and the contribution may be restored.'])->assertRedirect();
        $this->assertSame('visible', $post->refresh()->moderation_status);
    }
}
