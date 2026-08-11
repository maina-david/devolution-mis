<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\CountyGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GrantWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolution_admin_can_update_a_grant_record(): void
    {
        $county = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id, 'allocated_amount' => 1000, 'disbursed_amount' => 0]);
        $admin = User::factory()->devolutionAdmin()->create();
        Notification::fake();

        $this->actingAs($admin)->patch(route('grants.update', [$admin->currentTeam->slug, $grant]), [
            'allocated_amount' => 1200,
            'disbursed_amount' => 800,
            'status' => 'disbursed',
        ])->assertRedirect();

        $grant->refresh();
        $this->assertSame('1200.00', $grant->allocated_amount);
        $this->assertSame('800.00', $grant->disbursed_amount);
        $this->assertSame('disbursed', $grant->status);
    }

    public function test_disbursement_cannot_exceed_allocation(): void
    {
        $county = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id]);
        $admin = User::factory()->devolutionAdmin()->create();

        $this->actingAs($admin)->patch(route('grants.update', [$admin->currentTeam->slug, $grant]), [
            'allocated_amount' => 100,
            'disbursed_amount' => 101,
            'status' => 'processing',
        ])->assertSessionHasErrors('disbursed_amount');
    }

    public function test_read_only_grant_roles_cannot_update_grants(): void
    {
        $county = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id]);

        foreach ([User::factory()->countyAdmin($county)->create(), User::factory()->developmentPartner()->create()] as $user) {
            if ($user->programmeRole()->hasAssignedCountyScope()) {
                $user->assignedCounties()->attach($county);
            }

            $this->actingAs($user)->patch(route('grants.update', [$user->currentTeam->slug, $grant]), [
                'allocated_amount' => 100,
                'disbursed_amount' => 50,
                'status' => 'processing',
            ])->assertForbidden();
        }
    }
}
