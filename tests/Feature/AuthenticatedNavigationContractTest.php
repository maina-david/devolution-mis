<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthenticatedNavigationContractTest extends TestCase
{
    public function test_authenticated_sidebar_uses_compact_permission_aware_work_areas(): void
    {
        $registry = $this->source('resources/js/lib/app-navigation.ts');

        foreach (['Dashboard', 'County services', 'Delivery coordination', 'Performance & insights', 'Knowledge & capability', 'Platform governance', 'Platform administration'] as $group) {
            $this->assertStringContainsString("title: '{$group}'", $registry);
        }
        $platformGovernance = $this->sourceBetween(
            $registry,
            "title: 'Platform governance'",
            "title: 'Platform administration'",
        );
        $platformAdministration = substr(
            $registry,
            strpos($registry, "title: 'Platform administration'"),
        );
        foreach (['User access', 'Roles & permissions', 'Audit trail', 'Audit assurance', 'User activity', 'Data governance', 'Security governance'] as $title) {
            $this->assertStringContainsString("title: '{$title}'", $platformGovernance);
            $this->assertStringNotContainsString("title: '{$title}'", $platformAdministration);
        }
        foreach (['Reference data', 'Historical migrations', 'Workflow registry', 'Assessment setup', 'Integrations', 'Operations', 'Platform controls'] as $title) {
            $this->assertStringContainsString("title: '{$title}'", $platformAdministration);
            $this->assertStringNotContainsString("title: '{$title}'", $platformGovernance);
        }
        $this->assertStringContainsString('permissions.includes(permission)', $registry);
        $this->assertStringContainsString("title: 'Service desk'", $registry);
        $this->assertStringContainsString("can('support-desk:view')", $registry);

        $sidebar = $this->source('resources/js/components/app-sidebar.tsx');
        $this->assertStringContainsString('appNavigationGroups(', $sidebar);
        $this->assertStringContainsString('label={page.props.localization.common.work_areas}', $sidebar);
        $this->assertStringContainsString('page.props.localization.navigation', $sidebar);
        $navMain = $this->source('resources/js/components/nav-main.tsx');
        $this->assertStringNotContainsString('<Collapsible', $navMain);
        $this->assertStringNotContainsString('<SidebarMenuSub', $navMain);
        $this->assertStringNotContainsString('<NavUser', $sidebar);
        $this->assertStringNotContainsString('Department website', $sidebar);
        $this->assertStringNotContainsString('devolution.go.ke', $sidebar);
        $this->assertSame(1, substr_count($sidebar, '<NavMain'));
        $this->assertStringNotContainsString('group.items.map', $sidebar);
        $this->assertStringContainsString('href: group.items[0].href', $sidebar);
        $this->assertStringContainsString('group.showChildren === false ? undefined : group.items.length', $sidebar);
        $this->assertStringContainsString('group.showChildren === false ? undefined : group.items', $sidebar);
        $this->assertStringContainsString('showChildren: false', $this->source('resources/js/lib/app-navigation.ts'));

        $this->assertStringContainsString('<SidebarMenuBadge', $navMain);
        $this->assertStringContainsString('bg-sidebar-foreground/10', $navMain);
        $this->assertStringContainsString('pr-10', $navMain);
        $this->assertStringContainsString('[&>span:last-child]:line-clamp-2', $navMain);
        $sidebarPrimitive = $this->source('resources/js/components/ui/sidebar.tsx');
        $this->assertStringContainsString('top-1/2 right-1 flex h-5 min-w-5 -translate-y-1/2 items-center', $sidebarPrimitive);
        $this->assertStringNotContainsString('top-1/2 right-1 mt-', $sidebarPrimitive);
        $this->assertStringContainsString('px-1 text-xs leading-none font-medium tabular-nums', $sidebarPrimitive);
        $this->assertStringContainsString('const SIDEBAR_WIDTH = "18rem"', $sidebarPrimitive);
        $this->assertStringContainsString('side="right"', $navMain);
        $this->assertStringContainsString('item.subItems?.map', $navMain);
        $this->assertStringContainsString('onPointerEnter={openOnHover}', $navMain);
        $this->assertStringContainsString('onPointerEnter={retainOnContentHover}', $navMain);
        $this->assertStringContainsString('mouseHoverSession.current', $navMain);

        $this->assertStringContainsString('<SidebarFooter', $sidebar);
        $this->assertStringContainsString("user.permissions.includes('county-data:view')", $sidebar);
        $this->assertStringContainsString('href={evidenceIndex()}', $sidebar);
        $this->assertStringContainsString('.fileManager', $sidebar);
        $this->assertStringContainsString('notificationsIndex()', $sidebar);
        $this->assertStringContainsString('page.props.notificationSummary.unread', $sidebar);
        $this->assertStringContainsString('href={profileEdit()}', $sidebar);
        $this->assertStringContainsString("currentUrl.startsWith('/settings')", $sidebar);
        $this->assertStringContainsString('page.props.localization.copy.settings', $sidebar);
    }

    public function test_document_repository_renders_governed_county_identity_values(): void
    {
        $manager = $this->source('resources/js/components/document-repository-manager.tsx');

        $this->assertStringContainsString('county: CountyIdentityValue | null', $manager);
        $this->assertStringContainsString('<CountyIdentity', $manager);
        $this->assertStringContainsString('county={folder.county}', $manager);
        $this->assertStringContainsString('folder.county?.name', $manager);
        $this->assertStringNotContainsString('{folder.county ??', $manager);
    }

    public function test_document_repository_supports_grid_list_and_governed_drag_drop(): void
    {
        $workspace = $this->source('resources/js/pages/programme/workspace.tsx');
        $manager = $this->source('resources/js/components/document-repository-manager.tsx');
        $table = $this->source('resources/js/components/workspace-data-table.tsx');

        $this->assertStringContainsString("useState<'grid' | 'list'>", $workspace);
        $this->assertStringContainsString('localStorage.getItem(', $workspace);
        $this->assertStringContainsString('localStorage.setItem(', $workspace);
        $this->assertStringContainsString('idmis-evidence-view-mode', $workspace);
        $this->assertStringContainsString('displayMode={', $workspace);
        $this->assertStringContainsString('draggableRows={', $workspace);
        $this->assertStringContainsString('<ToggleGroup', $manager);
        $this->assertStringContainsString('moveDocuments.url()', $manager);
        $this->assertStringContainsString('onDrop={(event)', $manager);
        $this->assertStringContainsString('new DataTransfer()', $manager);
        $this->assertStringContainsString("displayMode === 'grid'", $table);
        $this->assertStringContainsString('application/x-idmis-document-ids', $table);
        $this->assertStringContainsString('draggable={draggableRows}', $table);
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

    public function test_service_desk_uses_the_shared_authenticated_workspace_contract(): void
    {
        $page = $this->source('resources/js/pages/support-desk/index.tsx');

        foreach ([
            '<FormSheet',
            '<Sheet',
            '<DateRangeFilter',
            '<WorkspaceDataTable',
            'bulkExport={{',
            '<DropdownMenuGroup>',
            'preview.url({',
            'download.url({',
            'cycles={[]}',
        ] as $contract) {
            $this->assertStringContainsString($contract, $page);
        }

        $this->assertStringNotContainsString('type="date"', $page);
        $this->assertStringNotContainsString('<Dialog', $page);
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
        $this->assertStringContainsString('onPointerEnter={openOnTriggerHover}', $header);
        $this->assertStringContainsString('onPointerEnter={openOnContentHover}', $header);
        $this->assertStringContainsString('onPointerLeave={closeAfterTriggerLeave}', $header);
        $this->assertStringContainsString('onPointerLeave={closeAfterContentLeave}', $header);
        $this->assertStringContainsString("event.pointerType !== 'mouse'", $header);
        $this->assertStringContainsString('modal={false} open={open} onOpenChange={handleOpenChange}', $header);
        $this->assertStringContainsString("triggerRef.current?.matches(':hover')", $header);
        $this->assertStringContainsString("contentRef.current?.matches(':hover')", $header);
        $this->assertStringContainsString('if (!nextOpen && mouseHoverSession.current)', $header);
        $this->assertStringContainsString('const pointerWithinMenu = useRef(false)', $header);
        $this->assertStringContainsString('const mouseHoverSession = useRef(false)', $header);
        $this->assertStringContainsString('pointerWithinMenu.current = true', $header);
        $this->assertStringContainsString('pointerWithinMenu.current = false', $header);
        $this->assertStringContainsString('mouseHoverSession.current = true', $header);
        $this->assertStringContainsString('mouseHoverSession.current = false', $header);
        $this->assertStringContainsString('onFocusOutside={(event) => {', $header);
        $this->assertStringContainsString('sideOffset={0}', $header);
        $this->assertStringContainsString('onCloseAutoFocus={(event) => event.preventDefault()}', $header);
        $this->assertStringContainsString('onEscapeKeyDown={dismissMenu}', $header);
        $this->assertStringContainsString('onPointerDownOutside={dismissMenu}', $header);
        $this->assertStringContainsString('<DropdownMenuGroup>', $header);
        $this->assertStringContainsString('aria-label={localization.copy.openAccountMenu}', $header);
        $this->assertStringContainsString('<UserInfo user={user} showRole', $header);
        $this->assertStringContainsString('aria-label={copy.chooseTheme}', $header);
        $this->assertStringContainsString('label={localization.copy.faqs}', $header);
        $this->assertStringContainsString('target="_blank"', $header);
        $this->assertStringContainsString('rel="noopener noreferrer"', $header);
        $this->assertStringContainsString('{copy.viewAllNotifications}', $header);
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
        $appLogoIcon = $this->source('resources/js/components/app-logo-icon.tsx');
        $this->assertStringContainsString('/images/branding/devolution-emblem.png', $appLogoIcon);
        $this->assertStringContainsString('aria-hidden="true"', $appLogoIcon);
        $this->assertStringContainsString('county?: CountyIdentityValue | null', $appLogo);
        $this->assertStringContainsString('<CountyIdentity', $appLogo);
        $this->assertStringContainsString('variant="outline"', $appLogo);
        $this->assertStringContainsString('text-sidebar-foreground', $appLogo);

        $navMain = $this->source('resources/js/components/nav-main.tsx');
        $this->assertStringContainsString('data-[active=true]:bg-sidebar-accent', $navMain);
        $this->assertStringContainsString('data-[active=true]:text-sidebar-accent-foreground', $navMain);

        $styles = $this->source('resources/css/app.css');
        $this->assertSame(2, substr_count($styles, '--sidebar: oklch(0.442 0.15 142.495)'));
        $this->assertSame(2, substr_count($styles, '--primary: oklch(0.442 0.15 142.495)'));
        $this->assertStringContainsString('html.dark {', $styles);
        $this->assertStringContainsString('--sidebar-accent: oklch(0.32 0.105 142.495)', $styles);

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
            'Security & data assurance',
            'Configuration',
            'Integrations & operations',
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
        $this->assertStringContainsString('if (!user)', $sidebarLayout);
        $this->assertStringContainsString('return children;', $sidebarLayout);

        $sidebar = $this->source('resources/js/components/app-sidebar.tsx');
        $this->assertStringContainsString('const user = page.props.auth.user;', $sidebar);
        $this->assertStringContainsString('if (!user)', $sidebar);
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
        $this->assertStringContainsString('triggerLabel={copy.compose_version}', $assessmentConfiguration);
        $this->assertStringNotContainsString('<details className="mt-4 rounded-xl border', $assessmentConfiguration);
    }

    public function test_grant_access_and_assessment_configuration_data_entry_uses_sheets(): void
    {
        $accessForm = $this->source('resources/js/components/programme-user-access-form.tsx');
        $this->assertStringContainsString('<FormSheet', $accessForm);
        $this->assertStringContainsString('triggerLabel={copy.grant_access}', $accessForm);
        $this->assertStringNotContainsString('<section className="rounded-xl border', $accessForm);

        $grantAction = $this->source('resources/js/components/grant-row-action.tsx');
        $this->assertStringContainsString('<DropdownMenu', $grantAction);
        $this->assertStringContainsString('<Sheet open={open}', $grantAction);
        $this->assertStringNotContainsString('className="ml-auto grid w-64', $grantAction);

        $configuration = $this->source('resources/js/pages/assessment-configuration/index.tsx');
        foreach (['triggerLabel={copy.create_scorecard}', 'triggerLabel={copy.create_cycle}', '<DatePickerField', '<SearchableSelect'] as $contract) {
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
        $this->assertStringContainsString('copy.map_boundary_notice', $map);
        $this->assertStringNotContainsString('<svg', $map);
        $this->assertStringNotContainsString('aspect-4/5', $partnerMap);
        $this->assertStringNotContainsString('aspect-4/5', $dashboard);
        $this->assertStringNotContainsString('max-w-lg items-center', $dashboard);
        $this->assertStringContainsString('<CardContent className="p-0">', $dashboard);
        $this->assertStringContainsString('<CardHeader className="py-6">', $dashboard);
        $this->assertStringContainsString('className="rounded-none border-x-0 border-b-0"', $dashboard);
        $this->assertStringContainsString('counties.length === 1 ? counties[0] : null', $dashboard);
        $this->assertStringContainsString("'Select an authorized county'", $dashboard);
        $this->assertStringNotContainsString('max-w-xl', $partnerMap);
        $this->assertStringContainsString('counties.length === 1 ? counties[0] : null', $partnerMap);
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

    private function sourceBetween(string $source, string $start, string $end): string
    {
        $startPosition = strpos($source, $start);
        $endPosition = strpos($source, $end);
        $this->assertIsInt($startPosition);
        $this->assertIsInt($endPosition);
        $this->assertGreaterThan($startPosition, $endPosition);

        return substr($source, $startPosition, $endPosition - $startPosition);
    }
}
