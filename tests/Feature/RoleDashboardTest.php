<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\CountyGrant;
use App\Models\User;
use App\Services\ProgrammeAuthorization;
use Database\Seeders\CountySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_official_dashboard_contains_only_home_county_statistics(): void
    {
        $home = County::factory()->create(['name' => 'Home County']);
        County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyOfficial($home)->create();
        $assessment = Assessment::factory()->create(['county_id' => $home->id, 'cycle' => '2025/26 ACPA']);
        AssessmentDocument::factory()->count(2)->create(['assessment_id' => $assessment->id, 'county_id' => $home->id]);
        CountyGrant::factory()->create(['county_id' => $home->id, 'allocated_amount' => 1000, 'disbursed_amount' => 600]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboardProfile.role', 'CountyOfficial')
            ->where('dashboardProfile.mapScope', 'county')
            ->where('stats.counties', 1)
            ->where('stats.documents', 2)
            ->has('counties', 1)
            ->where('counties.0.name', 'Home County')
        );
    }

    public function test_county_admin_dashboard_contains_only_home_county_data_and_map_scope(): void
    {
        $home = County::factory()->create(['name' => 'Admin Home County']);
        County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyAdmin($home)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboardProfile.role', 'CountyAdmin')
            ->where('dashboardProfile.mapScope', 'county')
            ->where('stats.counties', 1)
            ->has('counties', 1)
            ->where('counties.0.name', 'Admin Home County')
        );
    }

    public function test_dashboard_cycle_filter_limits_assessment_results_and_shares_cycle_options(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $selectedCycle = AssessmentCycle::factory()->create([
            'code' => 'ACPA-2025',
            'name' => '2025 ACPA',
        ]);
        $otherCycle = AssessmentCycle::factory()->create([
            'code' => 'ACPA-2024',
            'name' => '2024 ACPA',
            'period_start' => '2024-01-01',
            'period_end' => '2024-12-31',
        ]);
        Assessment::factory()->create([
            'county_id' => $county->id,
            'assessment_cycle_id' => $selectedCycle->id,
            'cycle' => $selectedCycle->code,
            'status' => 'approved',
            'score' => 72,
        ]);
        Assessment::factory()->create([
            'county_id' => $county->id,
            'assessment_cycle_id' => $otherCycle->id,
            'cycle' => $otherCycle->code,
            'status' => 'approved',
            'score' => 91,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['cycle_id' => $selectedCycle->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.cycle_id', $selectedCycle->id)
                ->where('stats.averageScore', 72)
                ->where('counties.0.latestScore', 72)
                ->has('assessmentCycles', 2)
                ->has('cycleOverview', 2)
                ->where('cycleOverview.0.name', '2025 ACPA')
                ->where('cycleOverview.0.selected', true)
                ->where('cycleOverview.0.countiesAssessed', 1)
                ->where('cycleOverview.0.completionPercent', 100)
                ->where('cycleOverview.0.averageScore', 72)
            );
    }

    public function test_cycle_overview_never_includes_assessments_from_unauthorized_counties(): void
    {
        $home = County::factory()->create();
        $hidden = County::factory()->create();
        $user = User::factory()->countyOfficial($home)->create();
        $cycle = AssessmentCycle::factory()->create(['name' => 'Scoped ACPA']);
        Assessment::factory()->create(['county_id' => $home->id, 'assessment_cycle_id' => $cycle->id, 'status' => 'approved', 'score' => 70]);
        Assessment::factory()->create(['county_id' => $hidden->id, 'assessment_cycle_id' => $cycle->id, 'status' => 'approved', 'score' => 10]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('cycleOverview.0.name', 'Scoped ACPA')
            ->where('cycleOverview.0.countiesTotal', 1)
            ->where('cycleOverview.0.countiesAssessed', 1)
            ->where('cycleOverview.0.averageScore', 70)
        );
    }

    public function test_operational_signals_are_limited_to_the_authorized_county_scope(): void
    {
        $home = County::factory()->create();
        $hidden = County::factory()->create();
        $user = User::factory()->countyAdmin($home)->create();

        CitizenCase::factory()->create([
            'county_id' => $home->id,
            'status' => 'in_progress',
            'resolution_due_at' => now()->subDay(),
        ]);
        CitizenCase::factory()->create([
            'county_id' => $hidden->id,
            'status' => 'in_progress',
            'resolution_due_at' => now()->subDay(),
        ]);
        CitizenCase::factory()->create([
            'county_id' => $home->id,
            'status' => 'resolved',
            'resolution_due_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('operationalSignals.overdueCitizenCases', 1)
            ->has('operationalSignals.activeProjects')
            ->has('operationalSignals.delayedExchequerRequests')
            ->has('operationalSignals.overdueEvaluationFindings')
            ->has('operationalSignals.openPartnerAlerts')
            ->has('operationalSignals.evidenceAwaitingReview')
            ->has('operationalSignals.evidenceScanAttention')
            ->has('roleFocus', 3)
        );
    }

    public function test_assessor_dashboard_contains_only_assigned_counties(): void
    {
        $assigned = County::factory()->count(2)->create();
        County::factory()->create();
        $user = User::factory()->assessor()->create();
        $user->assignedCounties()->attach($assigned);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboardProfile.role', 'Assessor')
            ->where('dashboardProfile.mapScope', 'country')
            ->where('stats.counties', 2)
            ->has('counties', 2)
        );
    }

    public function test_unassigned_portfolio_user_receives_an_empty_dashboard(): void
    {
        County::factory()->count(3)->create();
        $user = User::factory()->developmentPartner()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.counties', 0)
            ->has('counties', 0)
        );
    }

    public function test_devolution_admin_dashboard_contains_all_forty_seven_counties(): void
    {
        $this->seed(CountySeeder::class);
        $user = User::factory()->devolutionAdmin()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboardProfile.role', 'DevolutionAdmin')
            ->where('dashboardProfile.mapScope', 'country')
            ->where('stats.counties', 47)
            ->has('counties', 47)
        );
    }

    public function test_platform_admin_is_never_bound_to_a_county_context(): void
    {
        $county = County::factory()->create(['name' => 'Mombasa']);
        $user = User::factory()->create(['county_id' => $county->id]);
        $user->assignedCounties()->attach($county);

        app(ProgrammeAuthorization::class)->assignRole($user, UserRole::PlatformAdmin);

        $this->assertNull($user->fresh()->county_id);
        $this->assertSame(0, $user->assignedCounties()->count());

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboardProfile.role', 'PlatformAdmin')
            ->where('dashboardProfile.mapScope', 'none')
        );
    }

    public function test_every_programme_role_receives_its_own_dashboard_profile(): void
    {
        $titles = [
            UserRole::CountyOfficial->name => 'Evidence and assessment readiness',
            UserRole::CountyAdmin->name => 'County performance control',
            UserRole::Assessor->name => 'Assigned county assessments',
            UserRole::DevelopmentPartner->name => 'Programme results and grants',
            UserRole::TopManagement->name => 'County performance overview',
            UserRole::DevolutionAdmin->name => 'All-county delivery command',
            UserRole::PlatformAdmin->name => 'National platform control',
        ];
        $mapScopes = [
            UserRole::CountyOfficial->name => 'county',
            UserRole::CountyAdmin->name => 'county',
            UserRole::Assessor->name => 'country',
            UserRole::DevelopmentPartner->name => 'country',
            UserRole::TopManagement->name => 'portfolio',
            UserRole::DevolutionAdmin->name => 'country',
            UserRole::PlatformAdmin->name => 'none',
        ];

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create();
            app(ProgrammeAuthorization::class)->assignRole($user, $role);
            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk()->assertInertia(function (Assert $page) use ($role, $titles, $mapScopes): void {
                $page->where('dashboardProfile.role', $role->name)
                    ->where('dashboardProfile.roleLabel', $role->label())
                    ->where('dashboardProfile.title', $titles[$role->name])
                    ->where('dashboardProfile.mapScope', $mapScopes[$role->name])
                    ->has('dashboardProfile.description');
            });
        }
    }
}
