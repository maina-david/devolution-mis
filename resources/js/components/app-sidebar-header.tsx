import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Check,
    ChevronDown,
    CircleHelp,
    MessageCircleQuestion,
    Monitor,
    Moon,
    Sun,
} from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearchDialog } from '@/components/global-search-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    activeNavigationGroup,
    appNavigationGroups,
    contextualNavigationSections,
    fallbackNavigationBreadcrumb,
    navigationBreadcrumbs,
    navigationItemIsActive,
    settingsNavigationGroup,
} from '@/lib/app-navigation';
import type { ContextualNavigationSection } from '@/lib/app-navigation';
import { cn } from '@/lib/utils';
import { faqs, help } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import type { BreadcrumbItem as BreadcrumbItemType, NavItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage();
    const { auth, currentTeam, notificationSummary } = page.props;
    const { appearance, updateAppearance } = useAppearance();
    const { currentUrl } = useCurrentUrl();
    const groups = currentTeam
        ? appNavigationGroups(currentTeam.slug, auth.user.permissions)
        : [];
    const activeGroup =
        settingsNavigationGroup(currentUrl) ??
        activeNavigationGroup(groups, currentUrl);
    const registryBreadcrumbs = navigationBreadcrumbs(groups, currentUrl);
    const resolvedBreadcrumbs = breadcrumbs.length
        ? breadcrumbs
        : registryBreadcrumbs.length
          ? registryBreadcrumbs
          : [fallbackNavigationBreadcrumb(currentUrl)];
    const contextualSections = activeGroup
        ? contextualNavigationSections(activeGroup)
        : [];
    const primaryContextItems =
        contextualSections.find((section) => section.title === null)?.items ??
        [];
    const contextualSubgroups = contextualSections.filter(
        (section): section is typeof section & { title: string } =>
            section.title !== null,
    );

    return (
        <header className="app-sidebar-header sticky top-0 z-50 w-full shrink-0 border-b border-sidebar-border bg-sidebar text-sidebar-foreground shadow-xs">
            <div className="flex h-16 items-center gap-2 px-4 sm:px-6">
                <SidebarTrigger className="-ml-1 text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground" />
                <div className="min-w-0 flex-1">
                    <Breadcrumbs breadcrumbs={resolvedBreadcrumbs} inverse />
                </div>
                {currentTeam && <GlobalSearchDialog />}
                <HeaderLink href={help()} label="Help">
                    <CircleHelp />
                </HeaderLink>
                <HeaderLink href={faqs()} label="Frequently asked questions">
                    <MessageCircleQuestion />
                </HeaderLink>
                <ThemeMenu
                    appearance={appearance}
                    updateAppearance={updateAppearance}
                />
                {currentTeam && (
                    <NotificationMenu
                        teamSlug={currentTeam.slug}
                        summary={notificationSummary}
                    />
                )}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="h-10 w-10 gap-2 px-1 text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground sm:h-auto sm:min-h-10 sm:w-auto sm:max-w-64 sm:px-2 sm:py-1 [&>div:last-child]:hidden sm:[&>div:last-child]:grid"
                            aria-label="Open account menu"
                        >
                            <UserInfo user={auth.user} showRole />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-64" align="end">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {activeGroup && activeGroup.items.length > 1 && (
                <ContextualNavigation
                    label={`${activeGroup.title} pages`}
                    sections={contextualSections}
                    primaryItems={primaryContextItems}
                    subgroups={contextualSubgroups}
                    currentUrl={currentUrl}
                />
            )}
        </header>
    );
}

function ContextualNavigation({
    label,
    sections,
    primaryItems,
    subgroups,
    currentUrl,
}: {
    label: string;
    sections: ContextualNavigationSection[];
    primaryItems: NavItem[];
    subgroups: Array<ContextualNavigationSection & { title: string }>;
    currentUrl: string;
}) {
    const viewportRef = useRef<HTMLDivElement>(null);
    const measurementRef = useRef<HTMLDivElement>(null);
    const [groupsCollapsed, setGroupsCollapsed] = useState(false);
    const allItems = sections.flatMap((section) => section.items);

    useLayoutEffect(() => {
        const updateLayout = () => {
            const availableWidth = viewportRef.current?.clientWidth ?? 0;
            const requiredWidth = measurementRef.current?.scrollWidth ?? 0;

            if (availableWidth > 0 && requiredWidth > 0) {
                setGroupsCollapsed(requiredWidth > availableWidth);
            }
        };
        const observer = new ResizeObserver(updateLayout);

        if (viewportRef.current) {
            observer.observe(viewportRef.current);
        }

        if (measurementRef.current) {
            observer.observe(measurementRef.current);
        }

        updateLayout();

        return () => observer.disconnect();
    }, [allItems.length, label]);

    return (
        <nav
            aria-label={label}
            className="relative overflow-x-auto border-t border-primary-foreground/15 px-4 sm:px-6"
        >
            <div
                ref={measurementRef}
                aria-hidden="true"
                className="pointer-events-none invisible absolute flex min-w-max gap-1"
            >
                {allItems.map((item) => (
                    <span
                        key={item.title}
                        className="px-3 py-3 text-sm font-medium"
                    >
                        {item.title}
                    </span>
                ))}
            </div>
            <div ref={viewportRef} className="min-w-0">
                <div className="flex min-w-max gap-1">
                    {(groupsCollapsed ? primaryItems : allItems).map((item) => (
                        <ContextualTab
                            key={item.title}
                            item={item}
                            currentUrl={currentUrl}
                        />
                    ))}
                    {groupsCollapsed &&
                        subgroups.map((subgroup) => (
                            <ContextualGroupMenu
                                key={subgroup.title}
                                subgroup={subgroup}
                                currentUrl={currentUrl}
                            />
                        ))}
                </div>
            </div>
        </nav>
    );
}

function ContextualTab({
    item,
    currentUrl,
}: {
    item: NavItem;
    currentUrl: string;
}) {
    const active = navigationItemIsActive(item, currentUrl);

    return (
        <Link
            href={item.href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                'relative px-3 py-3 text-sm font-medium text-primary-foreground/75 transition-colors hover:text-primary-foreground focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-foreground',
                active &&
                    'text-primary-foreground after:absolute after:right-3 after:bottom-0 after:left-3 after:h-0.5 after:rounded-full after:bg-primary-foreground',
            )}
        >
            {item.title}
        </Link>
    );
}

function ContextualGroupMenu({
    subgroup,
    currentUrl,
}: {
    subgroup: ContextualNavigationSection & { title: string };
    currentUrl: string;
}) {
    const [open, setOpen] = useState(false);
    const closeTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const pointerWithinMenu = useRef(false);
    const mouseHoverSession = useRef(false);
    const active = subgroup.items.some((item) =>
        navigationItemIsActive(item, currentUrl),
    );
    const cancelClose = () => {
        if (closeTimeout.current !== null) {
            clearTimeout(closeTimeout.current);
            closeTimeout.current = null;
        }
    };
    const openOnTriggerHover = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        cancelClose();
        pointerWithinMenu.current = true;
        mouseHoverSession.current = true;
        setOpen(true);
    };
    const openOnContentHover = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        cancelClose();
        pointerWithinMenu.current = true;
        mouseHoverSession.current = true;
    };
    const closeAfterTriggerLeave = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        pointerWithinMenu.current = false;
        scheduleClose();
    };
    const closeAfterContentLeave = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        pointerWithinMenu.current = false;
        scheduleClose();
    };
    const pointerIsOverMenu = () =>
        pointerWithinMenu.current ||
        triggerRef.current?.matches(':hover') === true ||
        contentRef.current?.matches(':hover') === true;
    const scheduleClose = () => {
        cancelClose();
        closeTimeout.current = setTimeout(() => {
            if (!pointerIsOverMenu()) {
                mouseHoverSession.current = false;
                setOpen(false);
            }
        }, 200);
    };
    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && mouseHoverSession.current) {
            return;
        }

        setOpen(nextOpen);
    };
    const dismissMenu = () => {
        cancelClose();
        pointerWithinMenu.current = false;
        mouseHoverSession.current = false;
        setOpen(false);
    };

    useEffect(
        () => () => {
            if (closeTimeout.current !== null) {
                clearTimeout(closeTimeout.current);
            }
        },
        [],
    );

    return (
        <DropdownMenu modal={false} open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    ref={triggerRef}
                    variant="ghost"
                    aria-current={active ? 'page' : undefined}
                    onPointerEnter={openOnTriggerHover}
                    onPointerLeave={closeAfterTriggerLeave}
                    className={cn(
                        'relative h-auto rounded-none px-3 py-3 text-sm font-medium text-primary-foreground/75 hover:bg-primary-foreground/10 hover:text-primary-foreground',
                        active &&
                            'text-primary-foreground after:absolute after:right-3 after:bottom-0 after:left-3 after:h-0.5 after:rounded-full after:bg-primary-foreground',
                    )}
                >
                    {subgroup.title}
                    <ChevronDown data-icon="inline-end" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                ref={contentRef}
                align="start"
                sideOffset={0}
                onPointerEnter={openOnContentHover}
                onPointerLeave={closeAfterContentLeave}
                onFocusOutside={(event) => {
                    if (pointerIsOverMenu()) {
                        event.preventDefault();
                    }
                }}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onEscapeKeyDown={dismissMenu}
                onPointerDownOutside={dismissMenu}
            >
                <DropdownMenuLabel>{subgroup.title}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    {subgroup.items.map((item) => {
                        const itemActive = navigationItemIsActive(
                            item,
                            currentUrl,
                        );

                        return (
                            <DropdownMenuItem
                                key={item.title}
                                asChild
                                onSelect={dismissMenu}
                            >
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={
                                        itemActive ? 'page' : undefined
                                    }
                                >
                                    {item.title}
                                    {itemActive && (
                                        <Check className="ml-auto" />
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        );
                    })}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function HeaderLink({
    href,
    label,
    children,
}: {
    href: ReturnType<typeof help>;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <Button
            variant="ghost"
            size="icon"
            asChild
            title={label}
            className="text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
        >
            <Link
                href={href}
                aria-label={label}
                target="_blank"
                rel="noopener noreferrer"
            >
                {children}
            </Link>
        </Button>
    );
}

function ThemeMenu({
    appearance,
    updateAppearance,
}: {
    appearance: Appearance;
    updateAppearance: (appearance: Appearance) => void;
}) {
    const options: Array<{
        value: Appearance;
        label: string;
        icon: typeof Sun;
    }> = [
        { value: 'light', label: 'Light', icon: Sun },
        { value: 'dark', label: 'Dark', icon: Moon },
        { value: 'system', label: 'System', icon: Monitor },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
                    title="Theme"
                    aria-label="Choose theme"
                >
                    {appearance === 'dark' ? <Moon /> : <Sun />}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Theme</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {options.map(({ value, label, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => updateAppearance(value)}
                    >
                        <Icon />
                        {label}
                        {appearance === value && <Check className="ml-auto" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function NotificationMenu({
    teamSlug,
    summary,
}: {
    teamSlug: string;
    summary: ReturnType<typeof usePage>['props']['notificationSummary'];
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
                    title="Notifications"
                    aria-label={`Notifications${summary.unread ? `, ${summary.unread} unread` : ''}`}
                >
                    <Bell />
                    {summary.unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-bold text-destructive-foreground">
                            {summary.unread > 99 ? '99+' : summary.unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-80" align="end">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notifications</span>
                    <span className="text-xs font-normal text-muted-foreground">
                        {summary.unread} unread
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {summary.recent.length === 0 ? (
                    <div className="px-3 py-6 text-center text-sm text-muted-foreground">
                        No notifications yet.
                    </div>
                ) : (
                    summary.recent.map((notification) => (
                        <DropdownMenuItem key={notification.id} asChild>
                            <Link
                                href={
                                    notification.url ??
                                    notificationsIndex(teamSlug)
                                }
                                className="flex flex-col items-start gap-1 py-2"
                            >
                                <span className="flex w-full items-center gap-2 font-medium">
                                    {!notification.readAt && (
                                        <span className="size-2 rounded-full bg-primary" />
                                    )}
                                    <span className="truncate">
                                        {notification.title}
                                    </span>
                                </span>
                                <span className="line-clamp-2 text-xs text-muted-foreground">
                                    {notification.message}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={notificationsIndex(teamSlug)}>
                        View all notifications
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
