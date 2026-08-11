<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_roles_can_access_only_their_home_county(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();

        foreach ([User::factory()->countyOfficial($home)->create(), User::factory()->countyAdmin($home)->create()] as $user) {
            $this->assertTrue($user->canAccessCounty($home));
            $this->assertFalse($user->canAccessCounty($other));
            $this->assertTrue($user->can('view', $home));
            $this->assertFalse($user->can('view', $other));
        }
    }

    public function test_portfolio_roles_can_access_multiple_assigned_counties_only(): void
    {
        $assigned = County::factory()->count(2)->create();
        $other = County::factory()->create();

        foreach ([User::factory()->assessor()->create(), User::factory()->developmentPartner()->create(), User::factory()->topManagement()->create()] as $user) {
            $user->assignedCounties()->attach($assigned);
            $this->assertTrue($user->canAccessCounty($assigned[0]));
            $this->assertTrue($user->canAccessCounty($assigned[1]));
            $this->assertFalse($user->canAccessCounty($other));
        }
    }

    public function test_national_administrators_can_access_every_county(): void
    {
        $counties = County::factory()->count(3)->create();

        foreach ([User::factory()->devolutionAdmin()->create(), User::factory()->platformAdmin()->create()] as $user) {
            foreach ($counties as $county) {
                $this->assertTrue($user->canAccessCounty($county));
            }
        }
    }
}
