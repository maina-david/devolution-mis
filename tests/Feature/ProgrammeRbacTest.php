<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProgrammeRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_has_an_explicit_permission_profile(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotEmpty($role->permissions());
            $this->assertContains(ProgrammePermission::ViewDashboard, $role->permissions());
            $this->assertContains(ProgrammePermission::ViewCountyData, $role->permissions());
        }
    }

    public function test_separation_of_duties_is_enforced_by_permissions(): void
    {
        $countyOfficial = User::factory()->countyOfficial()->create();
        $countyAdmin = User::factory()->countyAdmin()->create();
        $assessor = User::factory()->assessor()->create();
        $topManagement = User::factory()->topManagement()->create();
        $devolutionAdmin = User::factory()->devolutionAdmin()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->assertTrue(Gate::forUser($countyOfficial)->allows(ProgrammePermission::UploadEvidence->value));
        $this->assertFalse(Gate::forUser($countyOfficial)->allows(ProgrammePermission::SubmitAssessment->value));
        $this->assertTrue(Gate::forUser($countyAdmin)->allows(ProgrammePermission::SubmitAssessment->value));
        $this->assertFalse(Gate::forUser($countyAdmin)->allows(ProgrammePermission::ScoreAssessment->value));
        $this->assertTrue(Gate::forUser($assessor)->allows(ProgrammePermission::ScoreAssessment->value));
        $this->assertFalse(Gate::forUser($assessor)->allows(ProgrammePermission::ApproveAssessment->value));
        $this->assertFalse(Gate::forUser($assessor)->allows(ProgrammePermission::ViewAuditTrail->value));
        $this->assertTrue(Gate::forUser($topManagement)->allows(ProgrammePermission::ApproveAssessment->value));
        $this->assertFalse(Gate::forUser($topManagement)->allows(ProgrammePermission::ConfigurePlatform->value));
        $this->assertFalse(Gate::forUser($topManagement)->allows(ProgrammePermission::ViewAuditTrail->value));
        $this->assertTrue(Gate::forUser($devolutionAdmin)->allows(ProgrammePermission::ManageGrants->value));
        $this->assertFalse(Gate::forUser($devolutionAdmin)->allows(ProgrammePermission::ConfigurePlatform->value));
        $this->assertTrue(Gate::forUser($devolutionAdmin)->allows(ProgrammePermission::ViewAuditTrail->value));
        $this->assertTrue(Gate::forUser($platformAdmin)->allows(ProgrammePermission::ConfigurePlatform->value));
        $this->assertFalse(Gate::forUser($platformAdmin)->allows(ProgrammePermission::ApproveAssessment->value));
        $this->assertTrue(Gate::forUser($platformAdmin)->allows(ProgrammePermission::ViewAuditTrail->value));
    }

    public function test_permission_does_not_bypass_county_scope(): void
    {
        $assignedCounty = County::factory()->create();
        $unassignedCounty = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($assignedCounty);

        $this->assertTrue($assessor->can(ProgrammePermission::ViewCountyData->value));
        $this->assertTrue($assessor->can('view', $assignedCounty));
        $this->assertFalse($assessor->can('view', $unassignedCounty));
    }
}
