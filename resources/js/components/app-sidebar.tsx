import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import NotificationRealtimeSync from '@/components/notification-realtime-sync';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    activeNavigationGroup,
    appNavigationGroups,
} from '@/lib/app-navigation';
import { dashboard, home } from '@/routes';

export function AppSidebar() {
    const page = usePage();
    const { currentUrl } = useCurrentUrl();
    const teamSlug = page.props.currentTeam?.slug;
    const dashboardUrl = teamSlug ? dashboard(teamSlug) : home();
    const navigationGroups = teamSlug
        ? appNavigationGroups(teamSlug, page.props.auth.user.permissions)
        : [];
    const activeGroup = activeNavigationGroup(navigationGroups, currentUrl);
    const groups = navigationGroups.map((group) => ({
        title: group.title,
        href: group.items[0].href,
        icon: group.icon,
        isActive: group === activeGroup,
        badge: group.items.length,
        subItems: group.items,
    }));

    return (
        <>
            <NotificationRealtimeSync />
            <Sidebar collapsible="icon" variant="inset">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={dashboardUrl} prefetch>
                                    <AppLogo
                                        county={
                                            page.props.auth.user.county_identity
                                        }
                                    />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <TeamSwitcher />
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>
                <SidebarContent>
                    <NavMain items={groups} label="Work areas" />
                </SidebarContent>
            </Sidebar>
        </>
    );
}
