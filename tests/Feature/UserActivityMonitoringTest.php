<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Models\UserPageView;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserActivityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_session_is_traced_from_login_through_page_view_to_logout(): void
    {
        $user = User::factory()->countyOfficial()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();

        $session = UserActivitySession::query()->where('user_id', $user->id)->sole();
        $this->assertSame('dashboard', $session->current_route);
        $this->assertSame('page.viewed', $session->last_action);
        $this->assertNull($session->logged_out_at);

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertNotNull($session->refresh()->logged_out_at);
        $this->assertSame('auth.logout', $session->last_action);
        $this->assertSame(['auth.login', 'auth.logout'], AuditEvent::query()->where('actor_id', $user->id)->orderBy('occurred_at')->orderBy('id')->pluck('action')->all());
        $this->assertSame($session->id, UserPageView::query()->where('user_id', $user->id)->sole()->user_activity_session_id);
    }

    public function test_only_platform_admin_can_view_live_presence_and_single_user_timeline(): void
    {
        $subject = User::factory()->countyOfficial()->create();
        $activitySession = UserActivitySession::factory()->for($subject)->create(['current_page_title' => 'County performance']);
        AuditEvent::factory()->for($subject, 'actor')->create(['subject_type' => $activitySession->getMorphClass(), 'subject_id' => $activitySession->id, 'action' => 'page.viewed', 'description' => 'Viewed County performance.', 'metadata' => ['activity_session_id' => $activitySession->id, 'route_name' => 'counties.index'], 'occurred_at' => now()]);
        UserPageView::factory()->for($subject)->create(['user_activity_session_id' => $activitySession->id, 'page_title' => 'County performance']);
        $devolutionAdmin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($devolutionAdmin)->get(route('user-activity.index'))->assertForbidden();

        $platformAdmin = User::factory()->platformAdmin()->create();
        $this->actingAs($platformAdmin)->get(route('user-activity.index', ['user_id' => $subject->id, 'session_id' => $activitySession->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('user-activity/index')->has('activeSessions', 1)->has('sessions.data', 1)->where('sessions.data.0.user.id', $subject->id)->has('events.data', 1)->where('events.data.0.sessionId', $activitySession->id)->has('pageViews.data', 1)->where('onlineWindowMinutes', 5));

        $this->assertDatabaseHas('user_page_views', ['user_id' => $platformAdmin->id, 'route_name' => 'user-activity.index']);
    }

    public function test_page_access_evidence_is_database_immutable_and_inactive_sessions_are_closed(): void
    {
        config()->set('session.lifetime', 30);
        $session = UserActivitySession::factory()->create(['last_seen_at' => now()->subMinutes(45)]);
        $pageView = UserPageView::factory()->for($session->user)->create(['user_activity_session_id' => $session->id]);

        $this->assertSame(0, Artisan::call('activity:expire-sessions'));
        $this->assertSame('auth.session_expired', $session->refresh()->last_action);
        $this->assertNotNull($session->logged_out_at);

        $this->expectException(QueryException::class);
        $pageView->update(['page_title' => 'Tampered']);
    }
}
