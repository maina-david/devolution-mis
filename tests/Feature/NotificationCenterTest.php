<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_notifications_and_unread_summary(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $other = User::factory()->countyAdmin($county)->create();
        $user->notifyNow(new ProgrammeAlert('Evidence received', 'The ADP evidence was uploaded.', 'evidence'));
        $other->notifyNow(new ProgrammeAlert('Hidden event', 'This belongs to another user.', 'access'));

        $this->actingAs($user)->get(route('notifications.index', $user->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('notificationSummary.unread', 1)
            ->has('notificationSummary.recent', 1)
            ->where('notificationSummary.recent.0.title', 'Evidence received')
            ->where('notificationSummary.recent.0.message', 'The ADP evidence was uploaded.')
            ->has('notifications', 1)
            ->where('notifications.0.title', 'Evidence received')
        );
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $user->notifyNow(new ProgrammeAlert('Assessment submitted', 'Submission received.', 'assessment'));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)->patch(route('notifications.read', [$user->currentTeam->slug, $notification]))->assertRedirect();

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $other = User::factory()->countyAdmin($county)->create();
        $other->notifyNow(new ProgrammeAlert('Private event', 'Another user owns this.', 'access'));
        $notification = $other->notifications()->firstOrFail();

        $this->actingAs($user)->patch(route('notifications.read', [$user->currentTeam->slug, $notification]))->assertForbidden();
        $this->assertNull($notification->fresh()?->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $user->notifyNow(new ProgrammeAlert('First', 'First event.', 'assessment'));
        $user->notifyNow(new ProgrammeAlert('Second', 'Second event.', 'evidence'));

        $this->actingAs($user)->patch(route('notifications.read-all', $user->currentTeam->slug))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_programme_alert_has_database_and_reverb_delivery_with_safe_payload(): void
    {
        $alert = new ProgrammeAlert('Live update', 'A broadcast event.', 'assessment', '/assessments');
        $user = User::factory()->make();
        $message = $alert->toBroadcast($user);

        $this->assertSame(['database', 'broadcast'], $alert->via($user));
        $this->assertInstanceOf(BroadcastMessage::class, $message);
        $this->assertSame(['title' => 'Live update', 'message' => 'A broadcast event.', 'category' => 'assessment', 'url' => '/assessments'], $message->data);
    }

    public function test_private_notification_channel_accepts_only_the_exact_authenticated_uuid(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $other = User::factory()->countyOfficial($county)->create();

        $authorization = Broadcast::getChannels()->get('App.Models.User.{id}');

        $this->assertIsCallable($authorization);
        $this->assertTrue($authorization($user, $user->id));
        $this->assertFalse($authorization($user, $other->id));
    }

    public function test_programme_alert_dispatches_the_framework_broadcast_notification_event(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $events = [];
        $listener = function (BroadcastNotificationCreated $event) use (&$events): void {
            $events[] = $event;
        };
        app(Dispatcher::class)->listen(BroadcastNotificationCreated::class, $listener);

        $user->notifyNow(new ProgrammeAlert('Realtime update', 'Delivered over Reverb.', 'assessment'));

        $this->assertCount(1, $events);
        $this->assertSame($user->id, $events[0]->notifiable->id);
        $this->assertSame('Realtime update', $events[0]->data['title']);
        app(Dispatcher::class)->forget(BroadcastNotificationCreated::class);
    }
}
