<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryEvidenceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_rollout_and_uat_are_not_product_routes_permissions_or_schema(): void
    {
        $removedRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'change-readiness'))
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $removedRoutes);
        $this->assertSame([], Permission::query()->whereIn('name', [
            'change-readiness:view',
            'change-readiness:manage',
            'training-evidence:record',
            'uat-evidence:record',
            'rollout-readiness:approve',
        ])->pluck('name')->all());

        foreach ([
            'rollout_waves',
            'county_rollout_wave',
            'training_cohorts',
            'training_participants',
            'training_assessments',
            'uat_campaigns',
            'uat_scenarios',
            'uat_executions',
            'uat_findings',
            'uat_acceptances',
            'county_uat_campaign',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} belongs to external delivery evidence, not the IDMIS product schema.");
        }
    }

    public function test_operational_learning_and_support_features_remain_available(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)->get(route('learning.index'))->assertOk();
        $this->actingAs($administrator)->get(route('support-desk.index'))->assertOk();
        $this->assertTrue($administrator->can(ProgrammePermission::ManageLearning->value));
        $this->assertTrue($administrator->can(ProgrammePermission::ConfigureSupportDesk->value));
    }
}
