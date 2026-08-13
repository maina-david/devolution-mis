import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { localization, sidebarOpen: isOpen } = usePage().props;
    const skipLink = (
        <a
            href="#main-content"
            className="fixed top-3 left-3 z-50 -translate-y-20 rounded-md bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-sm transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
            {localization.copy.skipToMainContent}
        </a>
    );

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">
                {skipLink}
                {children}
            </div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen}>
            {skipLink}
            {children}
        </SidebarProvider>
    );
}
