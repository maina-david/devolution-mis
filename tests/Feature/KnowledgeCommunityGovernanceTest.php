<?php

namespace Tests\Feature;

use App\Actions\CreateKnowledgeCommunityReport;
use App\Actions\CreateKnowledgePost;
use App\Actions\ModerateKnowledgePost;
use App\Actions\TransitionKnowledgeCommunityReport;
use App\Actions\UpdateKnowledgeDiscussionSubscription;
use App\Models\County;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgeItem;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        $this->actingAs($creator)->post(route('knowledge.discussions.store'), [
            'county_id' => $county->id,
            'title' => 'County peer-learning forum',
            'prompt' => 'Which verified practices should counties adapt and measure?',
            'visibility' => 'county',
        ])->assertRedirect();

        $discussion = KnowledgeDiscussion::query()->sole();
        $subscription = KnowledgeDiscussionSubscription::query()->sole();
        $this->assertTrue(Str::isUuid($subscription->id));
        $this->assertSame($creator->id, $subscription->user_id);

        $route = route('knowledge.discussions.subscription', [$discussion]);
        $this->actingAs($creator)->patch($route, ['subscribed' => true])->assertRedirect();
        $this->assertSame(1, KnowledgeDiscussionSubscription::withTrashed()->count());
        $this->actingAs($creator)->patch($route, ['subscribed' => false])->assertRedirect();
        $this->assertSoftDeleted($subscription);
        $this->actingAs($creator)->patch($route, ['subscribed' => true])->assertRedirect();
        $this->assertNotNull($subscription->fresh());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $discussion->id, 'action' => 'knowledge.discussion.subscribed']);

        $this->actingAs($outsider)->patch(route('knowledge.discussions.subscription', [$discussion]), ['subscribed' => true])->assertForbidden();
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

        $this->actingAs($author)->post(route('knowledge.posts.store', [$discussion]), [
            'body' => 'A verified peer-learning contribution with a documented county implementation result.',
        ])->assertRedirect();

        $post = KnowledgePost::query()->sole();
        $this->assertSame('visible', $post->moderation_status);
        $this->assertFalse($post->is_moderated);
        Notification::assertSentTo($subscriber, ProgrammeAlert::class, fn (ProgrammeAlert $notification): bool => $notification->category === 'knowledge'
            && $notification->messageTranslationKey === 'knowledge.notifications.new_contribution_message'
            && $notification->messageTranslationParameters['title'] === $discussion->title);
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
        $route = route('knowledge.posts.moderate', [$post]);
        $payload = ['moderation_status' => 'hidden', 'moderation_reason' => 'The contribution contains an unverified assertion requiring evidence before republication.'];

        $this->actingAs($author)->patch(route('knowledge.posts.moderate', [$post]), $payload)->assertForbidden();
        $this->actingAs($outsider)->patch(route('knowledge.posts.moderate', [$post]), $payload)->assertForbidden();
        $this->actingAs($curator)->patch($route, ['moderation_status' => 'hidden', 'moderation_reason' => 'Too short'])->assertSessionHasErrors('moderation_reason');
        $this->actingAs($curator)->patch($route, $payload)->assertRedirect();

        $post->refresh();
        $this->assertSame('hidden', $post->moderation_status);
        $this->assertSame('The original contribution remains available as immutable moderation context.', $post->body);
        $this->assertSame($curator->id, $post->moderated_by);
        $this->assertNotNull($post->moderated_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $post->id, 'action' => 'knowledge.post.moderated']);
        $this->actingAs($author)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page->where('items.data.0.discussions.0.posts', []));
        $this->actingAs($curator)->get(route('knowledge.index'))->assertOk()->assertInertia(fn ($page) => $page->where('items.data.0.discussions.0.posts.0.moderationStatus', 'hidden')->where('items.data.0.discussions.0.posts.0.body', $post->body));

        $this->actingAs($curator)->patch($route, ['moderation_status' => 'visible', 'moderation_reason' => 'The supporting evidence was independently verified and the contribution may be restored.'])->assertRedirect();
        $this->assertSame('visible', $post->refresh()->moderation_status);
    }

    public function test_community_actions_enforce_localized_permissions_before_record_or_payload_processing(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $author = User::factory()->countyOfficial($county)->create();
        $unauthorizedActor = User::factory()->create();
        $unauthorizedActor->syncRoles([]);
        $outOfScopeContributor = User::factory()->countyOfficial($otherCounty)->create();
        $outOfScopeCurator = User::factory()->topManagement()->create();
        $outOfScopeCurator->assignedCounties()->attach($otherCounty);
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $author->id]);
        $post = KnowledgePost::factory()->create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $author->id]);
        $report = KnowledgeCommunityReport::factory()->create(['knowledge_post_id' => $post->id, 'county_id' => $county->id, 'reported_by' => $author->id, 'workflow_instance_id' => null]);

        app()->setLocale('fr');
        $this->assertForbiddenAction(
            fn () => app(CreateKnowledgeCommunityReport::class)->handle($post, $unauthorizedActor, []),
            'Vous n’êtes pas autorisé à signaler des contributions de la communauté de connaissances.',
        );
        $this->assertForbiddenAction(
            fn () => app(CreateKnowledgePost::class)->handle($discussion, $unauthorizedActor, ''),
            'Vous n’êtes pas autorisé à contribuer aux discussions de la communauté de connaissances.',
        );
        $this->assertForbiddenAction(
            fn () => app(ModerateKnowledgePost::class)->handle($post, $unauthorizedActor, '', ''),
            'Vous n’êtes pas autorisé à modérer les contributions de la communauté de connaissances.',
        );
        $this->assertForbiddenAction(
            fn () => app(TransitionKnowledgeCommunityReport::class)->handle($report, $unauthorizedActor, '', '', null, null),
            'Vous n’êtes pas autorisé à faire évoluer les signalements de la communauté de connaissances.',
        );
        $this->assertForbiddenAction(
            fn () => app(UpdateKnowledgeDiscussionSubscription::class)->handle($discussion, $unauthorizedActor, true),
            'Vous n’êtes pas autorisé à suivre les discussions de la communauté de connaissances.',
        );
        foreach ([
            fn () => app(CreateKnowledgeCommunityReport::class)->handle($post, $outOfScopeContributor, []),
            fn () => app(CreateKnowledgePost::class)->handle($discussion, $outOfScopeContributor, ''),
            fn () => app(ModerateKnowledgePost::class)->handle($post, $outOfScopeCurator, '', ''),
            fn () => app(TransitionKnowledgeCommunityReport::class)->handle($report, $outOfScopeCurator, '', '', null, null),
            fn () => app(UpdateKnowledgeDiscussionSubscription::class)->handle($discussion, $outOfScopeContributor, true),
        ] as $outOfScopeAction) {
            $this->assertForbiddenAction(
                $outOfScopeAction,
                'Cet enregistrement de la communauté de connaissances est hors du périmètre de comté autorisé.',
            );
        }

        $this->assertDatabaseCount('knowledge_posts', 1);
        $this->assertDatabaseCount('knowledge_community_reports', 1);
        $this->assertDatabaseCount('knowledge_discussion_subscriptions', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_subscription_action_records_localized_governance_evidence(): void
    {
        $county = County::factory()->create();
        $participant = User::factory()->countyOfficial($county)->create();
        $discussion = KnowledgeDiscussion::factory()->create(['county_id' => $county->id, 'created_by' => $participant->id, 'title' => 'Jukwaa la mafunzo ya kaunti']);

        app()->setLocale('sw');
        app(UpdateKnowledgeDiscussionSubscription::class)->handle($discussion, $participant, true);

        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $discussion->id,
            'action' => 'knowledge.discussion.subscribed',
            'description' => 'Umejisajili kufuatilia Jukwaa la mafunzo ya kaunti.',
        ]);
    }

    /** @param callable(): mixed $action */
    private function assertForbiddenAction(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('The knowledge-community action did not enforce its permission boundary.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
