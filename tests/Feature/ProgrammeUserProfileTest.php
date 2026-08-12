<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\User;
use App\Models\UserActivitySession;
use App\Models\UserPageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgrammeUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_view_extensive_user_activity_and_correlated_audit_history(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->devolutionAdmin()->create();
        UserActivitySession::factory()->for($target)->create(['current_page_title' => 'Assessment configuration']);
        UserPageView::factory()->for($target)->create(['page_title' => 'County assessments']);
        AuditEvent::factory()->for($target, 'actor')->create(['action' => 'assessment.approved']);
        AuditEvent::factory()->create(['subject_type' => $target->getMorphClass(), 'subject_id' => $target->id, 'action' => 'access.reviewed']);

        $this->actingAs($admin)
            ->get(route('programme-users.show', [$target]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme-users/show')
                ->where('profile.id', $target->id)
                ->where('profile.role.value', 'devolution-admin')
                ->where('summary.sessionCount', 1)
                ->where('summary.pageViewCount', 1)
                ->where('summary.auditEventCount', 2)
                ->where('capabilities.viewActivity', true)
                ->where('capabilities.viewAudit', true)
                ->has('sessions.rows', 1)
                ->has('pageViews.rows', 1)
                ->has('auditEvents.rows', 2)
                ->has('accessGovernance.lifecycleRequests', 0));
    }

    public function test_county_administrator_can_view_own_county_user_without_sensitive_activity(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $target = User::factory()->countyOfficial($county)->create();
        UserActivitySession::factory()->for($target)->create();

        $this->actingAs($admin)
            ->get(route('programme-users.show', [$target]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.id', $target->id)
                ->where('capabilities.viewActivity', false)
                ->where('capabilities.viewAudit', false)
                ->where('summary.sessionCount', null)
                ->where('summary.pageViewCount', null)
                ->where('summary.auditEventCount', null)
                ->where('sessions', null)
                ->where('pageViews', null)
                ->where('auditEvents', null)
                ->where('accessGovernance', null));
    }

    public function test_county_administrator_cannot_view_user_from_another_county(): void
    {
        $admin = User::factory()->countyAdmin(County::factory()->create())->create();
        $target = User::factory()->countyOfficial(County::factory()->create())->create();

        $this->actingAs($admin)
            ->get(route('programme-users.show', [$target]))
            ->assertForbidden();
    }
}
