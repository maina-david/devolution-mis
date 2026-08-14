<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgrammeWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{UserRole, list<string>, list<string>}> */
    public static function roleRouteProvider(): iterable
    {
        yield 'county official' => [UserRole::CountyOfficial, ['counties.index', 'assessments.index', 'evidence.index', 'grants.index'], ['reports.index', 'programme-users.index', 'audit.index', 'platform.index']];
        yield 'county admin' => [UserRole::CountyAdmin, ['counties.index', 'assessments.index', 'evidence.index', 'grants.index', 'programme-users.index'], ['reports.index', 'audit.index', 'platform.index']];
        yield 'assessor' => [UserRole::Assessor, ['counties.index', 'assessments.index', 'evidence.index'], ['grants.index', 'reports.index', 'programme-users.index', 'audit.index', 'platform.index']];
        yield 'development partner' => [UserRole::DevelopmentPartner, ['counties.index', 'assessments.index', 'evidence.index', 'grants.index', 'reports.index'], ['programme-users.index', 'audit.index', 'platform.index']];
        yield 'top management' => [UserRole::TopManagement, ['counties.index', 'assessments.index', 'evidence.index', 'grants.index', 'reports.index'], ['programme-users.index', 'audit.index', 'platform.index']];
        yield 'devolution admin' => [UserRole::DevolutionAdmin, ['counties.index', 'assessments.index', 'evidence.index', 'grants.index', 'reports.index', 'programme-users.index', 'audit.index'], ['platform.index']];
        yield 'platform admin' => [UserRole::PlatformAdmin, ['counties.index', 'assessments.index', 'evidence.index', 'reports.index', 'programme-users.index', 'audit.index', 'platform.index'], ['grants.index']];
    }

    /**
     * @param  list<string>  $allowedRoutes
     * @param  list<string>  $forbiddenRoutes
     */
    #[DataProvider('roleRouteProvider')]
    public function test_each_role_can_open_only_its_authorized_workspaces(UserRole $role, array $allowedRoutes, array $forbiddenRoutes): void
    {
        $county = County::factory()->create();
        $user = $this->userForRole($role, $county);

        foreach ($allowedRoutes as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }

        foreach ($forbiddenRoutes as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_county_workspace_never_exposes_another_county_to_county_staff(): void
    {
        $home = County::factory()->create(['name' => 'Visible County']);
        County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyAdmin($home)->create();

        $this->actingAs($user)->get(route('counties.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('programme/workspace')
            ->has('workspace.rows', 1)
            ->where('workspace.rows.0.cells.0.kind', 'county')
            ->where('workspace.rows.0.cells.0.name', 'Visible County')
        );
    }

    public function test_user_access_county_options_include_the_verified_identity(): void
    {
        $county = County::factory()->create([
            'name' => 'Mombasa',
            'code' => 1,
            'logo_path' => '/images/counties/mombasa.webp',
            'logo_source_authority' => 'The National Treasury – Bajeti Yetu',
            'logo_verified_at' => '2026-08-10',
        ]);
        $user = User::factory()->countyAdmin($county)->create();

        $this->actingAs($user)->get(route('programme-users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.accessOptions.counties.0.kind', 'county')
            ->where('workspace.accessOptions.counties.0.name', 'Mombasa')
            ->where('workspace.accessOptions.counties.0.logoUrl', '/images/counties/mombasa.webp')
            ->where('workspace.accessOptions.counties.0.logoSourceAuthority', 'The National Treasury – Bajeti Yetu')
            ->where('workspace.accessOptions.counties.0.logoVerifiedAt', '2026-08-10'));
    }

    public function test_shared_authenticated_workspace_heroes_follow_the_active_locale(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('counties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.title', 'Utendaji wa kaunti')
                ->where('workspace.description', 'Rekodi za kaunti zilizoidhinishwa, ueneaji wa tathmini, utayari wa ushahidi na shughuli za ruzuku.')
            );

        $this->actingAs($user)
            ->withSession(['locale' => 'fr'])
            ->get(route('evidence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.title', 'Bibliothèque de preuves')
                ->where('workspace.description', 'Registre sécurisé des plans, avis d’audit, rapports de participation publique, textes législatifs et pièces justificatives.')
            );
    }

    private function userForRole(UserRole $role, County $county): User
    {
        $factory = match ($role) {
            UserRole::CountyOfficial => User::factory()->countyOfficial($county),
            UserRole::CountyAdmin => User::factory()->countyAdmin($county),
            UserRole::Assessor => User::factory()->assessor(),
            UserRole::DevelopmentPartner => User::factory()->developmentPartner(),
            UserRole::TopManagement => User::factory()->topManagement(),
            UserRole::DevolutionAdmin => User::factory()->devolutionAdmin(),
            UserRole::PlatformAdmin => User::factory()->platformAdmin(),
        };

        $user = $factory->create();

        if ($role->hasAssignedCountyScope()) {
            $user->assignedCounties()->attach($county);
        }

        return $user;
    }
}
