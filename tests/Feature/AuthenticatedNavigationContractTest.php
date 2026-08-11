<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthenticatedNavigationContractTest extends TestCase
{
    public function test_authenticated_sidebar_uses_compact_permission_aware_work_areas(): void
    {
        $registry = $this->source('resources/js/lib/app-navigation.ts');

        foreach (['Dashboard', 'County services', 'Delivery coordination', 'Performance & insights', 'Knowledge & capability', 'Platform governance'] as $group) {
            $this->assertStringContainsString("title: '{$group}'", $registry);
        }
        $this->assertStringContainsString('permissions.includes(permission)', $registry);

        $sidebar = $this->source('resources/js/components/app-sidebar.tsx');
        $this->assertStringContainsString('appNavigationGroups(', $sidebar);
        $this->assertStringContainsString('label="Work areas"', $sidebar);
        $navMain = $this->source('resources/js/components/nav-main.tsx');
        $this->assertStringNotContainsString('<Collapsible', $navMain);
        $this->assertStringNotContainsString('<SidebarMenuSub', $navMain);
        $this->assertStringNotContainsString('<NavUser', $sidebar);
        $this->assertStringNotContainsString('Department website', $sidebar);
        $this->assertStringNotContainsString('devolution.go.ke', $sidebar);
        $this->assertSame(1, substr_count($sidebar, '<NavMain'));
        $this->assertStringNotContainsString('group.items.map', $sidebar);
        $this->assertStringContainsString('href: group.items[0].href', $sidebar);
    }

    public function test_sidebar_contexts_reference_all_fourteen_tor_modules(): void
    {
        $registry = $this->source('resources/js/lib/app-navigation.ts');

        foreach ([
            'Citizen cases',
            'E-Learning',
            'Partners',
            'Sector working groups',
            'Projects',
            'Departmental performance',
            'Monitoring & evaluation',
            'Evidence repository',
            'Analytics',
            'Reports',
            'IGR resolutions',
            'Assessments',
            'Travel clearance',
            'Knowledge management',
        ] as $moduleNavigationTitle) {
            $this->assertStringContainsString(
                "title: '{$moduleNavigationTitle}'",
                $registry,
            );
        }
    }

    public function test_dashboard_does_not_duplicate_the_persistent_header_role_badge(): void
    {
        $dashboard = $this->source('resources/js/pages/dashboard.tsx');

        $this->assertStringNotContainsString(
            'w-fit border-white/20 bg-white/10 px-3 py-1.5 text-white',
            $dashboard,
        );
    }

    public function test_authenticated_header_exposes_context_tabs_and_utility_menus(): void
    {
        $header = $this->source('resources/js/components/app-sidebar-header.tsx');

        $this->assertStringContainsString('aria-current={active', $header);
        $this->assertStringContainsString('groupsCollapsed ? primaryItems : allItems', $header);
        $this->assertStringContainsString('subgroups.map', $header);
        $this->assertStringContainsString('contextualNavigationSections(activeGroup)', $header);
        $this->assertStringContainsString('new ResizeObserver(updateLayout)', $header);
        $this->assertStringContainsString('requiredWidth > availableWidth', $header);
        $this->assertStringContainsString('<ContextualTab', $header);
        $this->assertStringContainsString('<ContextualGroupMenu', $header);
        $this->assertStringContainsString('onPointerEnter={openOnPointerHover}', $header);
        $this->assertStringContainsString('onPointerLeave={closeAfterPointerLeave}', $header);
        $this->assertStringContainsString("event.pointerType !== 'mouse'", $header);
        $this->assertStringContainsString('open={open} onOpenChange={setOpen}', $header);
        $this->assertStringContainsString('<DropdownMenuGroup>', $header);
        $this->assertStringContainsString('aria-label="Open account menu"', $header);
        $this->assertStringContainsString('<UserInfo user={auth.user} showRole', $header);
        $this->assertStringContainsString('aria-label="Choose theme"', $header);
        $this->assertStringContainsString('Frequently asked questions', $header);
        $this->assertStringContainsString('target="_blank"', $header);
        $this->assertStringContainsString('rel="noopener noreferrer"', $header);
        $this->assertStringContainsString('View all notifications', $header);
        $this->assertStringContainsString('summary.recent.map', $header);
        $this->assertStringContainsString('sticky top-0 z-50', $header);
        $this->assertStringContainsString('bg-sidebar text-sidebar-foreground', $header);
        $this->assertStringContainsString('border-sidebar-border', $header);
        $this->assertStringContainsString('after:bg-primary-foreground', $header);
        $this->assertStringContainsString('navigationBreadcrumbs(groups, currentUrl)', $header);
        $this->assertStringContainsString('fallbackNavigationBreadcrumb(currentUrl)', $header);
        $this->assertStringContainsString('breadcrumbs={resolvedBreadcrumbs}', $header);
        $this->assertStringContainsString('inverse', $header);
        $this->assertStringContainsString('<GlobalSearchDialog', $header);
        $this->assertStringNotContainsString('Department website', $header);
        $this->assertStringNotContainsString('devolution.go.ke', $header);

        $userInfo = $this->source('resources/js/components/user-info.tsx');
        $this->assertStringContainsString('showRole = false', $userInfo);
        $this->assertStringContainsString('{user.role_label}', $userInfo);
        $this->assertStringContainsString('variant="secondary"', $userInfo);
        $this->assertStringNotContainsString('user.county_identity', $userInfo);

        $sidebar = $this->source('resources/js/components/app-sidebar.tsx');
        $this->assertStringContainsString('county={', $sidebar);
        $this->assertStringContainsString('.county_identity', $sidebar);
        $appLogo = $this->source('resources/js/components/app-logo.tsx');
        $this->assertStringContainsString('county?: CountyIdentityValue | null', $appLogo);
        $this->assertStringContainsString('<CountyIdentity', $appLogo);
        $this->assertStringContainsString('variant="outline"', $appLogo);
        $this->assertStringContainsString('text-sidebar-foreground', $appLogo);

        $navMain = $this->source('resources/js/components/nav-main.tsx');
        $this->assertStringContainsString('data-[active=true]:bg-sidebar-accent', $navMain);
        $this->assertStringContainsString('data-[active=true]:text-sidebar-accent-foreground', $navMain);

        $styles = $this->source('resources/css/app.css');
        $this->assertStringContainsString('--sidebar: oklch(0.49 0.105 158)', $styles);
        $this->assertStringContainsString('--primary: oklch(0.3 0.07 158)', $styles);
        $this->assertStringContainsString('html.dark {', $styles);
        $this->assertStringContainsString('--primary: oklch(0.205 0.035 158)', $styles);
        $this->assertStringContainsString('--sidebar: oklch(0.115 0.025 158)', $styles);
        $this->assertStringContainsString('--sidebar-accent: oklch(0.31 0.075 158)', $styles);

        $appearance = $this->source('resources/js/hooks/use-appearance.tsx');
        $this->assertStringContainsString("document.documentElement.classList.toggle('dark', isDark)", $appearance);
        $breadcrumbs = $this->source('resources/js/components/breadcrumbs.tsx');
        $this->assertStringContainsString("inverse && 'text-primary-foreground/85'", $breadcrumbs);
        $this->assertStringContainsString("'text-primary-foreground'", $breadcrumbs);
        $this->assertStringContainsString("'text-primary-foreground/65'", $breadcrumbs);

        $registry = $this->source('resources/js/lib/app-navigation.ts');
        foreach ([
            'County oversight',
            'Assessment & evidence',
            'Funding & operations',
            'Programmes & partnerships',
            'Intergovernmental coordination',
            'Results',
            'Analytics',
            'Reporting & performance',
            'Learning & readiness',
            'Knowledge exchange',
            'Access & accountability',
            'Configuration',
            'Assurance & operations',
        ] as $subgroup) {
            $this->assertStringContainsString("title: '{$subgroup}'", $registry);
        }

        $legacyHeader = $this->source('resources/js/components/app-header.tsx');
        $this->assertStringNotContainsString('Department website', $legacyHeader);
        $this->assertStringNotContainsString('devolution.go.ke', $legacyHeader);

        $this->assertStringContainsString('assignedTitles.has(title)', $registry);
        $this->assertStringContainsString('assignedTitles.add(title)', $registry);

        $sidebarLayout = $this->source('resources/js/layouts/app/app-sidebar-layout.tsx');
        $this->assertStringContainsString('overflow-x-clip', $sidebarLayout);
        $this->assertStringNotContainsString('overflow-x-hidden', $sidebarLayout);
    }

    public function test_authenticated_workspace_heroes_and_sheet_actions_have_consistent_contrast(): void
    {
        $formSheet = $this->source('resources/js/components/form-sheet.tsx');
        $this->assertStringContainsString('variant="secondary"', $formSheet);

        foreach (glob(resource_path('js/pages/**/*.tsx')) ?: [] as $page) {
            if (str_contains($page, '/auth/')) {
                continue;
            }

            $source = file_get_contents($page);
            $this->assertIsString($source, "Unable to read {$page}.");
            $this->assertStringNotContainsString(
                'rounded-2xl bg-primary px-6 py-7 text-primary-foreground',
                $source,
                "Authenticated workspace headers must use the compact shared treatment: {$page}",
            );
            $this->assertStringNotContainsString(
                'bg-[#12304a]',
                $source,
                "Authenticated workspaces must not use marketing-style hero cards: {$page}",
            );
        }

        $styles = $this->source('resources/css/app.css');
        $this->assertStringContainsString('.authenticated-page-header', $styles);
        $this->assertStringContainsString('@apply border-b border-border pb-4', $styles);
        $this->assertStringContainsString('.authenticated-page-header p', $styles);
        $this->assertStringContainsString('@apply text-muted-foreground', $styles);
        $this->assertStringContainsString('color: var(--foreground) !important', $styles);
        $this->assertStringContainsString('color: var(--muted-foreground) !important', $styles);
        $this->assertStringContainsString('opacity: 1', $styles);

        $assessmentConfiguration = $this->source('resources/js/pages/assessment-configuration/index.tsx');
        $this->assertStringContainsString('triggerLabel="Compose version"', $assessmentConfiguration);
        $this->assertStringNotContainsString('<details className="mt-4 rounded-xl border', $assessmentConfiguration);
    }

    public function test_grant_access_and_assessment_configuration_data_entry_uses_sheets(): void
    {
        $accessForm = $this->source('resources/js/components/programme-user-access-form.tsx');
        $this->assertStringContainsString('<FormSheet', $accessForm);
        $this->assertStringContainsString('triggerLabel="Grant access"', $accessForm);
        $this->assertStringNotContainsString('<section className="rounded-xl border', $accessForm);

        $grantAction = $this->source('resources/js/components/grant-row-action.tsx');
        $this->assertStringContainsString('<DropdownMenu', $grantAction);
        $this->assertStringContainsString('<Sheet open={open}', $grantAction);
        $this->assertStringNotContainsString('className="ml-auto grid w-64', $grantAction);

        $configuration = $this->source('resources/js/pages/assessment-configuration/index.tsx');
        foreach (['triggerLabel="Create scorecard"', 'triggerLabel="Create cycle"', '<DatePickerField', '<SearchableSelect'] as $contract) {
            $this->assertStringContainsString($contract, $configuration);
        }
        $this->assertStringNotContainsString('type="date"', $configuration);
        $this->assertStringNotContainsString('type="datetime-local"', $configuration);
        $this->assertStringNotContainsString('<select', $configuration);
    }

    public function test_county_map_uses_a_contextual_basemap_and_fits_authorized_boundaries(): void
    {
        $map = $this->source('resources/js/components/kenya-county-map.tsx');
        $partnerMap = $this->source('resources/js/components/partner-portfolio-map.tsx');
        $dashboard = $this->source('resources/js/pages/dashboard.tsx');

        $this->assertStringContainsString("void import('leaflet')", $map);
        $this->assertStringContainsString("import countyBoundaries from '@/data/kenya-county-boundaries-osm.json'", $map);
        $this->assertStringContainsString('showFullCountry ? kenyaCounties : countyBoundaries', $map);
        $this->assertStringContainsString('VITE_MAP_TILE_URL', $map);
        $this->assertStringContainsString('L.tileLayer(tileUrl', $map);
        $this->assertStringContainsString('map.attributionControl.setPrefix(', $map);
        $this->assertStringContainsString('IDMIS</span> · State Department for Devolution', $map);
        $this->assertStringContainsString('L.geoJSON(featureCollection', $map);
        $this->assertStringContainsString('map.fitBounds(mapBounds', $map);
        $this->assertStringContainsString('maxZoom: showFullCountry ? 7 : 12', $map);
        $this->assertStringContainsString('layer.bindTooltip', $map);
        $this->assertStringContainsString("layer.on('click', () => onSelect(county))", $map);
        $this->assertStringContainsString('relative isolate z-0 min-h-80', $map);
        $this->assertStringContainsString('County boundaries are indicative, not cadastral.', $map);
        $this->assertStringNotContainsString('<svg', $map);
        $this->assertStringNotContainsString('aspect-4/5', $partnerMap);
        $this->assertStringNotContainsString('aspect-4/5', $dashboard);
        $this->assertStringNotContainsString('max-w-lg items-center', $dashboard);
        $this->assertStringContainsString('<CardContent className="p-0">', $dashboard);
        $this->assertStringContainsString('<CardHeader className="py-6">', $dashboard);
        $this->assertStringContainsString('className="rounded-none border-x-0 border-b-0"', $dashboard);
        $this->assertStringNotContainsString('max-w-xl', $partnerMap);
        $this->assertStringContainsString('<CardContent className="p-0">', $partnerMap);
        $this->assertStringContainsString('<CardHeader className="py-6">', $partnerMap);
        $this->assertStringContainsString('className="rounded-none border-x-0 border-b-0"', $partnerMap);
    }

    public function test_zoomed_county_map_has_all_downloaded_osm_administrative_boundaries(): void
    {
        $source = file_get_contents(base_path('resources/js/data/kenya-county-boundaries-osm.json'));
        $this->assertIsString($source);
        $boundaries = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(47, $boundaries['features']);
        $this->assertCount(47, array_unique(array_column(array_column($boundaries['features'], 'properties'), 'ADM1_EN')));

        $mombasa = collect($boundaries['features'])->firstWhere('properties.ADM1_EN', 'Mombasa');
        $this->assertIsArray($mombasa);
        $this->assertSame('Polygon', $mombasa['geometry']['type']);
        $this->assertSame('3495554', (string) $mombasa['properties']['OSM_ID']);
        $this->assertSame('OpenStreetMap Nominatim', $mombasa['properties']['SOURCE']);
        $this->assertGreaterThan(100, count($mombasa['geometry']['coordinates'][0]));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source, "Unable to read {$path}.");

        return $source;
    }
}
