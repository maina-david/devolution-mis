<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessControlManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_view_role_matrix_and_direct_permission_register(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->platformAdmin()->create(['name' => 'Amina Administrator']);
        $target = User::factory()->assessor()->create(['name' => 'Zawadi Assessor']);
        $target->givePermissionTo(ProgrammePermission::ViewGrants->value);

        $this->actingAs($admin)
            ->get(route('access-control.index', $admin->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('access-control/index')
                ->has('roles', count(UserRole::cases()))
                ->has('permissions', count(ProgrammePermission::cases()))
                ->where('users.total', 2)
                ->has('users.data', 2));
    }

    public function test_role_permission_changes_are_atomic_audited_and_protect_platform_recovery_access(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $role = Role::query()->where('name', UserRole::CountyOfficial->value)->firstOrFail();
        $permissions = [ProgrammePermission::ViewDashboard->value, ProgrammePermission::ViewCountyData->value, ProgrammePermission::ViewProjects->value];

        $this->actingAs($admin)->patch(route('access-control.roles.update', [$admin->currentTeam->slug, $role->name]), [
            'permissions' => $permissions,
            'reason' => 'Approved least-privilege baseline update for county delivery users.',
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing($permissions, $role->fresh()->permissions->pluck('name')->all());
        $event = AuditEvent::query()->where('action', 'access.role_permissions.updated')->firstOrFail();
        $this->assertSame($role->id, $event->subject_id);
        $this->assertEqualsCanonicalizing($permissions, $event->metadata['after']);

        $this->actingAs($admin)->patch(route('access-control.roles.update', [$admin->currentTeam->slug, UserRole::PlatformAdmin->value]), [
            'permissions' => [ProgrammePermission::ViewDashboard->value],
            'reason' => 'Attempt to remove recovery controls must be rejected by policy.',
        ])->assertSessionHasErrors('permissions');

        $this->assertTrue(Role::query()->where('name', UserRole::PlatformAdmin->value)->firstOrFail()->hasPermissionTo(ProgrammePermission::ManageUserAccess->value));
    }

    public function test_direct_permissions_are_separate_from_role_inheritance_and_audited(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $direct = [ProgrammePermission::ViewGrants->value, ProgrammePermission::ViewNationalReports->value];

        $this->actingAs($admin)->patch(route('access-control.user-permissions.update', [$admin->currentTeam->slug, $target]), [
            'permissions' => $direct,
            'reason' => 'Time-bounded reporting support approved by the accountable programme owner.',
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing($direct, $target->fresh()->getDirectPermissions()->pluck('name')->all());
        $this->assertTrue($target->fresh()->hasPermissionTo(ProgrammePermission::ReviewAssessment->value));
        $event = AuditEvent::query()->where('action', 'access.direct_permissions.updated')->firstOrFail();
        $this->assertSame($target->id, $event->subject_id);
        $this->assertSame($direct, $event->metadata['after']);
    }

    public function test_administrators_cannot_assign_their_own_direct_permissions_and_county_admins_are_denied(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $countyAdmin = User::factory()->countyAdmin(County::factory()->create())->create();

        $this->actingAs($admin)->patch(route('access-control.user-permissions.update', [$admin->currentTeam->slug, $admin]), [
            'permissions' => [ProgrammePermission::ManageOperations->value],
            'reason' => 'Self-service privilege escalation must not be accepted by the platform.',
        ])->assertSessionHasErrors('permissions');

        $this->actingAs($countyAdmin)
            ->get(route('access-control.index', $countyAdmin->currentTeam->slug))
            ->assertForbidden();
    }
}
