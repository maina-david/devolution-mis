import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
    variant?: AppVariant;
};

export function AppContent({ variant = 'sidebar', children, ...props }: Props) {
    if (variant === 'sidebar') {
        return (
            <SidebarInset
                {...props}
                id="main-content"
                tabIndex={-1}
                className={cn('min-w-0 focus:outline-none', props.className)}
            >
                {children}
            </SidebarInset>
        );
    }

    return (
        <main
            {...props}
            id="main-content"
            tabIndex={-1}
            className={cn(
                'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl focus:outline-none',
                props.className,
            )}
        >
            {children}
        </main>
    );
}
