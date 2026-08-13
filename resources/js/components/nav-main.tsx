import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

type NavMainItem = NavItem & { subItems?: NavItem[] };

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavMainItem[];
    label?: string;
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-0">
            <SidebarGroupLabel className="px-2 text-[0.68rem] font-semibold tracking-wide text-sidebar-foreground/65 uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <NavMainRow
                        key={item.title}
                        item={item}
                        active={item.isActive ?? isCurrentUrl(item.href)}
                        isCurrentUrl={isCurrentUrl}
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavMainRow({
    item,
    active,
    isCurrentUrl,
}: {
    item: NavMainItem;
    active: boolean;
    isCurrentUrl: (href: NavItem['href']) => boolean;
}) {
    const [open, setOpen] = useState(false);
    const closeTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const triggerRef = useRef<HTMLAnchorElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const pointerWithinMenu = useRef(false);
    const mouseHoverSession = useRef(false);
    const hasSubItems = (item.subItems?.length ?? 0) > 0;
    const cancelClose = () => {
        if (closeTimeout.current !== null) {
            clearTimeout(closeTimeout.current);
            closeTimeout.current = null;
        }
    };
    const pointerIsOverMenu = () =>
        pointerWithinMenu.current ||
        triggerRef.current?.matches(':hover') === true ||
        contentRef.current?.matches(':hover') === true;
    const openOnHover = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse' || !hasSubItems) {
            return;
        }

        cancelClose();
        pointerWithinMenu.current = true;
        mouseHoverSession.current = true;
        setOpen(true);
    };
    const retainOnContentHover = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        cancelClose();
        pointerWithinMenu.current = true;
        mouseHoverSession.current = true;
    };
    const scheduleClose = (event: React.PointerEvent) => {
        if (event.pointerType !== 'mouse') {
            return;
        }

        pointerWithinMenu.current = false;
        cancelClose();
        closeTimeout.current = setTimeout(() => {
            if (!pointerIsOverMenu()) {
                mouseHoverSession.current = false;
                setOpen(false);
            }
        }, 200);
    };
    const dismissMenu = () => {
        cancelClose();
        pointerWithinMenu.current = false;
        mouseHoverSession.current = false;
        setOpen(false);
    };
    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && mouseHoverSession.current) {
            return;
        }

        setOpen(nextOpen);
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
        <SidebarMenuItem>
            <DropdownMenu
                modal={false}
                open={open}
                onOpenChange={handleOpenChange}
            >
                <DropdownMenuTrigger asChild disabled={!hasSubItems}>
                    <SidebarMenuButton
                        asChild
                        isActive={active}
                        tooltip={{ children: item.title }}
                        className="h-auto min-h-10 rounded-md py-2 pr-10 pl-3 font-medium text-sidebar-foreground hover:bg-sidebar-foreground/10 hover:text-sidebar-foreground data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:text-sidebar-accent-foreground [&>span:last-child]:line-clamp-2 [&>span:last-child]:whitespace-normal"
                    >
                        <Link
                            ref={triggerRef}
                            href={item.href}
                            prefetch
                            onPointerEnter={openOnHover}
                            onPointerLeave={scheduleClose}
                            onFocus={() => hasSubItems && setOpen(true)}
                        >
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    ref={contentRef}
                    side="right"
                    align="start"
                    sideOffset={8}
                    className="min-w-60"
                    onPointerEnter={retainOnContentHover}
                    onPointerLeave={scheduleClose}
                    onFocusOutside={(event) => {
                        if (pointerIsOverMenu()) {
                            event.preventDefault();
                        }
                    }}
                    onCloseAutoFocus={(event) => event.preventDefault()}
                    onEscapeKeyDown={dismissMenu}
                    onPointerDownOutside={dismissMenu}
                >
                    <DropdownMenuLabel>{item.title}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        {item.subItems?.map((subItem) => {
                            const subItemActive = isCurrentUrl(subItem.href);

                            return (
                                <DropdownMenuItem
                                    key={subItem.title}
                                    asChild
                                    onSelect={dismissMenu}
                                >
                                    <Link
                                        href={subItem.href}
                                        prefetch
                                        aria-current={
                                            subItemActive ? 'page' : undefined
                                        }
                                    >
                                        {subItem.icon && <subItem.icon />}
                                        {subItem.title}
                                        {subItemActive && (
                                            <Check className="ml-auto" />
                                        )}
                                    </Link>
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            {!!item.badge && (
                <SidebarMenuBadge className="top-1/2 right-2 -translate-y-1/2 bg-sidebar-foreground/12 text-sidebar-foreground">
                    {item.badge}
                </SidebarMenuBadge>
            )}
        </SidebarMenuItem>
    );
}
