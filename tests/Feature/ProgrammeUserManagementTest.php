<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProgrammeUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_admin_can_grant_county_official_access_only_in_own_county(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->countyAdmin($home)->create();
        Notification::fake();

        $this->actingAs($admin)->post(route('programme-users.store'), [
            'name' => 'New County Officer',
            'email' => 'new.official@county.go.ke',
            'role' => UserRole::CountyOfficial->value,
            'county_id' => $home->id,
        ])->assertRedirect();

        $created = User::query()->where('email', 'new.official@county.go.ke')->firstOrFail();
        $this->assertSame(UserRole::CountyOfficial, $created->programmeRole());
        $this->assertSame($home->id, $created->county_id);
        $this->assertFalse(Schema::hasTable('teams'));

        $this->actingAs($admin)->post(route('programme-users.store'), [
            'name' => 'Outside Officer',
            'email' => 'outside@county.go.ke',
            'role' => UserRole::CountyOfficial->value,
            'county_id' => $other->id,
        ])->assertSessionHasErrors('county_id');
    }

    public function test_county_admin_cannot_grant_elevated_roles(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();

        $this->actingAs($admin)->post(route('programme-users.store'), [
            'name' => 'Unauthorized Admin',
            'email' => 'elevated@county.go.ke',
            'role' => UserRole::DevolutionAdmin->value,
        ])->assertSessionHasErrors('role');
    }

    public function test_platform_admin_can_grant_portfolio_role_with_county_assignments(): void
    {
        $counties = County::factory()->count(2)->create();
        $admin = User::factory()->platformAdmin()->create();
        Notification::fake();

        $this->actingAs($admin)->post(route('programme-users.store'), [
            'name' => 'Independent Verifier',
            'email' => 'verifier@idmis.go.ke',
            'role' => UserRole::Assessor->value,
            'assigned_county_ids' => $counties->pluck('id')->all(),
        ])->assertRedirect();

        $created = User::query()->where('email', 'verifier@idmis.go.ke')->firstOrFail();
        $this->assertSame(UserRole::Assessor, $created->programmeRole());
        $this->assertCount(2, $created->assignedCounties);
    }

    public function test_county_admin_can_deactivate_official_but_not_another_admin(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $official = User::factory()->countyOfficial($county)->create();
        $otherAdmin = User::factory()->countyAdmin($county)->create();

        $this->actingAs($admin)->delete(route('programme-users.destroy', [$official]))->assertRedirect();
        $this->actingAs($admin)->delete(route('programme-users.destroy', [$otherAdmin]))->assertForbidden();

        $this->assertSoftDeleted($official);
        $this->assertNotSoftDeleted($otherAdmin);
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->delete(route('programme-users.destroy', [$admin]))->assertStatus(409);
        $this->assertNotSoftDeleted($admin);
    }
}
