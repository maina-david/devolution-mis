<?php

namespace Tests\Feature;

use App\Actions\GrantProgrammeAccess;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdministratorProvisioningBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_workspace_and_invitation_endpoints_do_not_exist(): void
    {
        $user = User::factory()->create();
        $requests = [
            ['get', '/settings/teams'],
            ['post', '/settings/teams'],
            ['get', '/settings/teams/obsolete-workspace'],
            ['patch', '/settings/teams/obsolete-workspace'],
            ['delete', '/settings/teams/obsolete-workspace'],
            ['post', '/invitations/01911111-1111-7111-8111-111111111111/accept'],
            ['delete', '/invitations/01911111-1111-7111-8111-111111111111'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->actingAs($user)->{$method}($uri)->assertNotFound();
        }

        foreach (['teams.index', 'teams.store', 'teams.edit', 'teams.update', 'teams.destroy', 'teams.leave', 'teams.switch', 'teams.members.update', 'teams.members.destroy', 'teams.invitations.store', 'teams.invitations.destroy', 'invitations.accept', 'invitations.decline'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "{$routeName} must not be registered.");
        }

        $this->assertFalse(Schema::hasTable('teams'));
        $this->assertFalse(Schema::hasTable('team_members'));
        $this->assertFalse(Schema::hasTable('team_invitations'));
    }

    public function test_login_and_dashboard_expose_no_invitation_workflow(): void
    {
        $user = User::factory()->create();

        $this->get(route('login', ['invitation' => 'obsolete-code']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->missing('teamInvitation'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->missing('pendingInvitations'));
    }

    public function test_governed_access_grant_creates_an_account_without_a_workspace(): void
    {
        Notification::fake();
        $administrator = User::factory()->platformAdmin()->create();

        $user = app(GrantProgrammeAccess::class)->handle([
            'name' => 'Amina Hassan',
            'email' => 'amina.hassan@example.test',
            'role' => UserRole::DevolutionAdmin->value,
            'assigned_county_ids' => [],
        ], $administrator, sendSetup: false);

        $this->assertFalse(Schema::hasColumn('users', 'current_team_id'));
        $this->assertFalse(Schema::hasTable('teams'));
        $this->assertSame(UserRole::DevolutionAdmin, $user->programmeRole());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $administrator->id,
            'subject_id' => $user->id,
            'action' => 'access.granted',
        ]);
    }

    public function test_removed_self_service_source_surfaces_are_absent(): void
    {
        foreach ([
            'app/Http/Controllers/Teams/TeamInvitationController.php',
            'app/Models/TeamInvitation.php',
            'resources/js/components/create-team-modal.tsx',
            'resources/js/components/invite-member-modal.tsx',
            'resources/js/components/team-switcher.tsx',
            'resources/js/pages/teams/index.tsx',
        ] as $path) {
            $this->assertFileDoesNotExist(base_path($path));
        }

        $navigation = file_get_contents(resource_path('js/lib/app-navigation.ts'));
        $this->assertIsString($navigation);
        $this->assertStringNotContainsString("title: 'Teams'", $navigation);
    }
}
