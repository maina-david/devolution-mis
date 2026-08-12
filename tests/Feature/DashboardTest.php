<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_personal_workspace_slugs_are_not_valid_dashboard_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/samuel-mutuas-workspace-019ff430/dashboard')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_authenticated_users_can_visit_the_dashboard_without_invitation_props(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->missing('pendingInvitations'));
    }

    public function test_county_staff_receive_governed_county_identity_in_the_authenticated_header(): void
    {
        $county = County::factory()->create([
            'name' => 'Kisumu',
            'code' => 42,
            'logo_path' => '/images/counties/kisumu.webp',
            'logo_source_authority' => 'The National Treasury',
        ]);
        $countyOfficial = User::factory()->countyOfficial()->create(['county_id' => $county->id]);
        $platformAdmin = User::factory()->platformAdmin()->create(['county_id' => $county->id]);

        $this->actingAs($countyOfficial)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.county_identity.kind', 'county')
                ->where('auth.user.county_identity.id', $county->id)
                ->where('auth.user.county_identity.name', 'Kisumu')
                ->where('auth.user.county_identity.logoUrl', '/images/counties/kisumu.webp'));

        $this->actingAs($platformAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.county_identity', null));
    }
}
