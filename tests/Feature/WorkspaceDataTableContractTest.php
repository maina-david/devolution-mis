<?php

namespace Tests\Feature;

use Tests\TestCase;

class WorkspaceDataTableContractTest extends TestCase
{
    public function test_shared_table_numbers_rows_and_exposes_page_size_control(): void
    {
        $source = $this->source('resources/js/components/workspace-data-table.tsx');

        $this->assertStringContainsString("id: 'row-number'", $source);
        $this->assertStringContainsString('(pagination.currentPage - 1) * pagination.perPage', $source);
        $this->assertStringContainsString('copy.rows_per_page', $source);
        $this->assertStringContainsString("url.searchParams.set(pagination.perPageName ?? 'per_page', value)", $source);
        $this->assertStringContainsString("url.searchParams.set(pagination.pageName ?? 'page', '1')", $source);
        $this->assertStringContainsString('interpolate(copy.records_range', $source);
    }

    public function test_shared_filter_bar_aligns_controls_and_preserves_page_size(): void
    {
        $source = $this->source('resources/js/components/date-range-filter.tsx');

        $this->assertStringContainsString('sm:flex-wrap sm:items-end', $source);
        $this->assertStringContainsString("'per_page'", $source);
        $this->assertStringContainsString('currentQuery.get(perPageKey)', $source);
        $this->assertStringContainsString('[perPageKey]: currentPerPage || undefined', $source);
        $this->assertStringContainsString('cycles ?? page.props.assessmentCycles', $source);
        $this->assertStringContainsString("currentQuery.get('cycle_id')", $source);
    }

    public function test_shared_table_columns_are_sortable_with_direction_feedback(): void
    {
        $source = $this->source('resources/js/components/workspace-data-table.tsx');

        $this->assertStringContainsString('getSortedRowModel: getSortedRowModel()', $source);
        $this->assertStringContainsString('onSortingChange: setSorting', $source);
        $this->assertStringContainsString('header.column.getToggleSortingHandler()', $source);
        $this->assertStringContainsString('ArrowUpDown', $source);
        $this->assertStringContainsString("cell.column.id === 'row-number'", $source);
    }

    public function test_shared_table_uses_the_shadcn_empty_state_for_zero_rows(): void
    {
        $source = $this->source('resources/js/components/workspace-data-table.tsx');
        $emptyState = $this->source('resources/js/components/table-empty-state.tsx');

        $this->assertStringContainsString('table.getRowModel().rows.length === 0', $source);
        $this->assertStringContainsString('<TableEmptyState />', $source);
        $this->assertStringContainsString("from '@/components/ui/empty'", $emptyState);
        $this->assertStringContainsString('<Empty className="border-0 py-10" role="status">', $emptyState);
        $this->assertStringContainsString('<EmptyTitle>{title ?? copy.no_records_found}</EmptyTitle>', $emptyState);
        $this->assertStringContainsString('description ?? copy.no_records_match_filters', $emptyState);
    }

    public function test_custom_management_tables_use_the_shared_shadcn_empty_state(): void
    {
        foreach (['resources/js/pages/access-control/index.tsx', 'resources/js/pages/reference-data/index.tsx'] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString("import TableEmptyState from '@/components/table-empty-state';", $source);
            $this->assertStringContainsString('<TableEmptyState', $source);
        }
    }

    public function test_settings_use_the_shared_contextual_tab_surface(): void
    {
        $navigation = $this->source('resources/js/lib/app-navigation.ts');
        $layout = $this->source('resources/js/layouts/settings/layout.tsx');

        $this->assertStringContainsString('settingsNavigationGroup', $navigation);
        foreach (['Profile', 'Security', 'Appearance'] as $tab) {
            $this->assertStringContainsString("translatedNavigationTitle('{$tab}', translations)", $navigation);
        }
        $this->assertStringNotContainsString("title: 'Teams'", $navigation);
        $this->assertStringNotContainsString('<aside', $layout);
        $this->assertStringNotContainsString('sidebarNavItems', $layout);
        $this->assertStringContainsString('w-full min-w-0', $layout);
        $this->assertStringNotContainsString('max-w-2xl', $layout);
    }

    public function test_wide_tables_are_contained_without_expanding_the_page(): void
    {
        $table = $this->source('resources/js/components/ui/table.tsx');
        $content = $this->source('resources/js/components/app-content.tsx');
        $citizenCases = $this->source('resources/js/pages/citizen-cases/index.tsx');

        $this->assertStringContainsString('min-w-0 max-w-full overflow-x-auto', $table);
        $this->assertStringContainsString('min-w-0 focus:outline-none', $content);
        $this->assertStringContainsString('max-w-full min-w-0 flex-1', $citizenCases);
        $this->assertStringContainsString('Card className="min-w-0 overflow-hidden"', $citizenCases);
    }

    public function test_record_rows_do_not_fall_back_to_county_navigation(): void
    {
        $workspace = $this->source('resources/js/pages/programme/workspace.tsx');
        $monitoring = $this->source('resources/js/pages/monitoring-evaluation/index.tsx');

        $this->assertStringContainsString("workspaceType === 'assessments'", $workspace);
        $this->assertStringContainsString("workspaceType === 'counties'", $workspace);
        $this->assertStringNotContainsString(': row.meta?.countyId', $workspace);
        $this->assertStringNotContainsString('getRowHref=', $monitoring);
    }

    public function test_partner_map_selection_updates_the_sidebar_without_navigation(): void
    {
        $source = $this->source('resources/js/components/partner-portfolio-map.tsx');

        $this->assertStringContainsString('onSelect={setSelected}', $source);
        $this->assertStringNotContainsString('router.visit', $source);
        $this->assertStringNotContainsString('@/routes/counties', $source);
    }

    public function test_nested_drilldowns_preserve_active_filters_without_parent_pagination(): void
    {
        $helper = $this->source('resources/js/lib/preserve-drilldown-filters.ts');
        $workspace = $this->source('resources/js/pages/programme/workspace.tsx');
        $projects = $this->source('resources/js/pages/projects/index.tsx');
        $analytics = $this->source('resources/js/pages/assessments/analytics.tsx');
        $dashboard = $this->source('resources/js/pages/dashboard.tsx');

        foreach (['from', 'to', 'search', 'cycle_id', 'status', 'county_id', 'programme_id', 'sector_id', 'partner_id', 'financial_year'] as $filter) {
            $this->assertStringContainsString("'{$filter}'", $helper);
        }
        $this->assertStringNotContainsString("'page'", $helper);
        $this->assertStringNotContainsString("'per_page'", $helper);
        foreach ([$workspace, $projects, $analytics, $dashboard] as $source) {
            $this->assertStringContainsString('preserveDrilldownFilters(', $source);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source, "Unable to read {$path}.");

        return $source;
    }
}
