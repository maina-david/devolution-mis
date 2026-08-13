<?php

namespace Tests\Feature;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsFilterView;
use App\Models\AnalyticsWidget;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsFilterViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_save_and_reuse_a_personal_filter_view(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->devolutionAdmin()->create();

        $this->actingAs($user)->post(route('analytics.filter-views.store'), [
            'name' => 'Published county view',
            'is_default' => true,
            'filters' => ['county_id' => $county->id, 'status' => 'published', 'from' => '2026-01-01', 'to' => '2026-12-31'],
        ])->assertRedirect();

        $view = AnalyticsFilterView::query()->sole();
        $this->assertTrue(Str::isUuid($view->id));
        $this->assertSame($user->id, $view->user_id);
        $this->assertTrue($view->is_default);
        $this->assertSame($county->id, $view->filters['county_id']);

        $this->actingAs($user)->get(route('analytics.index'))->assertRedirect(route('analytics.index', $view->filters));
        $this->actingAs($user)->get(route('analytics.index', $view->filters))->assertOk()->assertInertia(fn ($page) => $page
            ->has('savedFilterViews', 1)
            ->where('savedFilterViews.0.name', 'Published county view')
            ->where('savedFilterViews.0.isDefault', true));
    }

    public function test_only_one_default_view_is_retained_per_user(): void
    {
        $user = User::factory()->devolutionAdmin()->create();
        AnalyticsFilterView::factory()->for($user)->create(['name' => 'First', 'is_default' => true]);

        $this->actingAs($user)->post(route('analytics.filter-views.store'), [
            'name' => 'Second',
            'is_default' => true,
            'filters' => ['status' => 'draft'],
        ])->assertRedirect();

        $this->assertFalse(AnalyticsFilterView::query()->where('name', 'First')->sole()->is_default);
        $this->assertTrue(AnalyticsFilterView::query()->where('name', 'Second')->sole()->is_default);
    }

    public function test_user_cannot_delete_another_users_filter_view(): void
    {
        $owner = User::factory()->devolutionAdmin()->create();
        $other = User::factory()->devolutionAdmin()->create();
        $view = AnalyticsFilterView::factory()->for($owner)->create();

        $this->actingAs($other)->delete(route('analytics.filter-views.destroy', $view))->assertForbidden();
        $this->assertModelExists($view);

        $this->actingAs($owner)->delete(route('analytics.filter-views.destroy', $view))->assertRedirect();
        $this->assertSoftDeleted($view);
    }

    public function test_filter_view_outcomes_and_audit_evidence_follow_the_active_locale(): void
    {
        $owner = User::factory()->devolutionAdmin()->create();
        $view = AnalyticsFilterView::factory()->for($owner)->create(['name' => 'Mtazamo wa kaunti']);

        $this->actingAs($owner)
            ->withSession(['locale' => 'sw'])
            ->delete(route('analytics.filter-views.destroy', $view))
            ->assertRedirect()
            ->assertSessionHas('success', 'Mwonekano wa kichujio umeondolewa.');

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'subject_id' => $view->id,
            'action' => 'analytics.filter_view.deleted',
            'description' => 'Mwonekano wa kichujio cha uchanganuzi Mtazamo wa kaunti umeondolewa.',
        ]);
    }

    public function test_filter_view_rejects_unknown_keys_and_invalid_ranges(): void
    {
        $user = User::factory()->devolutionAdmin()->create();

        $this->actingAs($user)->post(route('analytics.filter-views.store'), [
            'name' => 'Invalid',
            'filters' => ['status' => 'removed', 'from' => '2026-12-31', 'to' => '2026-01-01', 'unsafe' => 'value'],
        ])->assertSessionHasErrors(['filters.status', 'filters.to']);

        $this->assertDatabaseCount('analytics_filter_views', 0);
    }

    public function test_saved_view_preserves_valid_governed_drill_down_and_chart_state(): void
    {
        $user = User::factory()->devolutionAdmin()->create();
        $dashboard = AnalyticsDashboard::factory()->for($user, 'creator')->create();
        $widget = AnalyticsWidget::factory()->for($dashboard, 'dashboard')->create();

        $filters = [
            'dashboard_id' => $dashboard->id,
            'widget_id' => $widget->id,
            'visualization' => 'line',
            'time_grain' => 'quarter',
        ];
        $this->actingAs($user)->post(route('analytics.filter-views.store'), [
            'name' => 'Quarterly dashboard drill-down',
            'filters' => $filters,
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing($filters, AnalyticsFilterView::query()->sole()->filters);
        $this->actingAs($user)->get(route('analytics.index', $filters))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.dashboard_id', $dashboard->id)
            ->where('filters.widget_id', $widget->id)
            ->where('filters.visualization', 'line')
            ->where('filters.time_grain', 'quarter'));

        $otherWidget = AnalyticsWidget::factory()->create();
        $this->actingAs($user)->post(route('analytics.filter-views.store'), [
            'name' => 'Invalid dashboard relationship',
            'filters' => [...$filters, 'widget_id' => $otherWidget->id],
        ])->assertSessionHasErrors('filters.widget_id');
    }
}
