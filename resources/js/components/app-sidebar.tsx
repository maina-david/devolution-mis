import { Link, usePage } from '@inertiajs/react';
import { Bell, Settings2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import NotificationRealtimeSync from '@/components/notification-realtime-sync';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    activeNavigationGroup,
    appNavigationGroups,
} from '@/lib/app-navigation';
import { dashboard, home } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as profileEdit } from '@/routes/profile';

export function AppSidebar() {
    const page = usePage();
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const user = page.props.auth.user;
    const dashboardUrl = user ? dashboard() : home();
    const navigationGroups = user ? appNavigationGroups(user.permissions) : [];
    const activeGroup = activeNavigationGroup(navigationGroups, currentUrl);
    const groups = navigationGroups.map((group) => ({
        title: group.title,
        href: group.items[0].href,
        icon: group.icon,
        isActive: group === activeGroup,
        badge: group.showChildren === false ? undefined : group.items.length,
        subItems: group.showChildren === false ? undefined : group.items,
    }));

    if (!user) {
        return null;
    }

    return (
        <>
            <NotificationRealtimeSync />
            <Sidebar
                collapsible="icon"
                variant="inset"
                className="border-r border-sidebar-border"
            >
                <SidebarHeader className="gap-2 border-b border-sidebar-border p-3">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                size="lg"
                                asChild
                                className="h-13 rounded-md hover:bg-sidebar-foreground/10"
                            >
                                <Link href={dashboardUrl} prefetch>
                                    <AppLogo county={user.county_identity} />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>
                <SidebarContent className="py-3">
                    <NavMain items={groups} label="Work areas" />
                </SidebarContent>
                <SidebarFooter className="border-t border-sidebar-border p-3">
                    <SidebarMenu className="gap-1">
                        {user && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(
                                        notificationsIndex(),
                                    )}
                                    tooltip={{
                                        children:
                                            page.props.localization.copy
                                                .notifications,
                                    }}
                                    className="min-h-10 rounded-md px-3 font-medium text-sidebar-foreground hover:bg-sidebar-foreground/10 hover:text-sidebar-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground"
                                >
                                    <Link href={notificationsIndex()} prefetch>
                                        <Bell aria-hidden="true" />
                                        <span>
                                            {
                                                page.props.localization.copy
                                                    .notifications
                                            }
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                                {page.props.notificationSummary.unread > 0 && (
                                    <SidebarMenuBadge className="right-2 bg-sidebar-foreground/12 text-sidebar-foreground">
                                        {page.props.notificationSummary.unread}
                                    </SidebarMenuBadge>
                                )}
                            </SidebarMenuItem>
                        )}
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={currentUrl.startsWith('/settings')}
                                tooltip={{
                                    children:
                                        page.props.localization.copy.settings,
                                }}
                                className="min-h-10 rounded-md px-3 font-medium text-sidebar-foreground hover:bg-sidebar-foreground/10 hover:text-sidebar-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground"
                            >
                                <Link href={profileEdit()} prefetch>
                                    <Settings2 aria-hidden="true" />
                                    <span>
                                        {page.props.localization.copy.settings}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>
        </>
    );
}
