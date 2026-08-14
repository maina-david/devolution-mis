<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationFuzzTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{UserRole}> */
    public static function unprivilegedRoleProvider(): iterable
    {
        yield 'county official' => [UserRole::CountyOfficial];
        yield 'county administrator' => [UserRole::CountyAdmin];
        yield 'assessor' => [UserRole::Assessor];
        yield 'development partner' => [UserRole::DevelopmentPartner];
        yield 'top management' => [UserRole::TopManagement];
    }

    #[DataProvider('unprivilegedRoleProvider')]
    public function test_unprivileged_roles_cannot_discover_or_open_platform_control_planes(UserRole $role): void
    {
        $user = $this->userForRole($role);

        $routeNames = ['access-control.index', 'audit-assurance.index', 'data-migrations.index'];
        if ($role !== UserRole::TopManagement) {
            $routeNames = [...$routeNames, 'operations.index', 'security-governance.index'];
        }

        foreach ($routeNames as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
        }
    }

    #[DataProvider('unprivilegedRoleProvider')]
    public function test_unprivileged_roles_cannot_stage_reference_or_user_imports(UserRole $role): void
    {
        $user = $this->userForRole($role);

        foreach (['organizations', 'users'] as $datasetType) {
            $this->actingAs($user)->post(route('data-migrations.reference-data.store'), [
                'dataset_type' => $datasetType,
                'source_name' => 'Hostile import attempt',
                'source_reference' => 'AUTH-FUZZ-DENIED',
            ])->assertForbidden();
        }

        $this->assertSame(0, DataMigrationBatch::query()->count());
        $this->assertSame(0, AuditEvent::query()->where('action', 'data_import.staged')->count());
    }

    #[DataProvider('unprivilegedRoleProvider')]
    public function test_method_override_and_hostile_permission_payloads_cannot_cross_access_control_boundary(UserRole $role): void
    {
        $user = $this->userForRole($role);
        $target = User::factory()->assessor()->create();
        $countyRole = Role::query()->where('name', UserRole::CountyOfficial->value)->firstOrFail();
        $originalRolePermissions = $countyRole->permissions()->pluck('name')->sort()->values()->all();

        $payloads = [
            ['permissions' => [ProgrammePermission::ManageUserAccess->value], 'reason' => str_repeat('privilege escalation ', 2)],
            ['permissions' => ['*', 'configure-platform', "view-dashboard\0manage-user-access"], 'reason' => str_repeat('hostile permission ', 2)],
            ['permissions' => array_fill(0, 64, ProgrammePermission::ConfigurePlatform->value), 'reason' => str_repeat('duplicate permission ', 2)],
        ];

        foreach ($payloads as $payload) {
            $this->actingAs($user)->post(route('access-control.roles.update', ['role' => $countyRole->name]), ['_method' => 'PATCH', ...$payload])->assertForbidden();
            $this->actingAs($user)->post(route('access-control.user-permissions.update', ['programmeUser' => $target]), ['_method' => 'PATCH', ...$payload])->assertForbidden();
        }

        $this->assertSame($originalRolePermissions, $countyRole->fresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->assertCount(0, $target->fresh()->getDirectPermissions());
    }

    public function test_guests_cannot_use_method_override_to_reach_any_privileged_control_plane(): void
    {
        foreach (['access-control.index', 'operations.index', 'audit-assurance.index', 'security-governance.index', 'data-migrations.index'] as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('unprivilegedRoleProvider')]
    public function test_direct_permission_route_does_not_disclose_whether_a_target_uuid_exists(UserRole $role): void
    {
        $user = $this->userForRole($role);
        $existingTarget = User::factory()->assessor()->create();
        $payload = [
            'permissions' => [ProgrammePermission::ViewGrants->value],
            'reason' => 'Hostile identifier enumeration must fail before resource resolution.',
        ];

        $this->actingAs($user)
            ->patch(route('access-control.user-permissions.update', ['programmeUser' => $existingTarget->id]), $payload)
            ->assertForbidden();
        $this->actingAs($user)
            ->patch(route('access-control.user-permissions.update', ['programmeUser' => (string) Str::uuid7()]), $payload)
            ->assertForbidden();

        $this->assertCount(0, $existingTarget->fresh()->getDirectPermissions());
    }

    public function test_privileged_actor_hostile_payloads_fail_atomically_without_audit_side_effects(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->assessor()->create();
        $role = Role::query()->where('name', UserRole::CountyOfficial->value)->firstOrFail();
        $originalRolePermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        $payloads = [
            ['permissions' => ProgrammePermission::ManageUserAccess->value, 'reason' => str_repeat('scalar payload ', 2)],
            ['permissions' => [[ProgrammePermission::ManageUserAccess->value]], 'reason' => str_repeat('nested payload ', 2)],
            ['permissions' => [ProgrammePermission::ManageUserAccess->value, ProgrammePermission::ManageUserAccess->value], 'reason' => str_repeat('duplicate payload ', 2)],
            ['permissions' => ['*', "manage-user-access\0configure-platform"], 'reason' => str_repeat('wildcard payload ', 2)],
        ];

        foreach ($payloads as $payload) {
            $this->actingAs($admin)
                ->patch(route('access-control.roles.update', ['role' => $role->name]), $payload)
                ->assertSessionHasErrors();
            $this->actingAs($admin)
                ->patch(route('access-control.user-permissions.update', ['programmeUser' => $target->id]), $payload)
                ->assertSessionHasErrors();
        }

        $this->assertSame($originalRolePermissions, $role->fresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->assertCount(0, $target->fresh()->getDirectPermissions());
        $this->assertSame(0, AuditEvent::query()->whereIn('action', [
            'access.role_permissions.updated',
            'access.direct_permissions.updated',
        ])->count());
    }

    private function userForRole(UserRole $role): User
    {
        $county = County::factory()->create();

        return match ($role) {
            UserRole::CountyOfficial => User::factory()->countyOfficial($county)->create(),
            UserRole::CountyAdmin => User::factory()->countyAdmin($county)->create(),
            UserRole::Assessor => User::factory()->assessor()->create(),
            UserRole::DevelopmentPartner => User::factory()->developmentPartner()->create(),
            UserRole::TopManagement => User::factory()->topManagement()->create(),
            default => throw new \LogicException('The provider must contain only unprivileged roles.'),
        };
    }
}
