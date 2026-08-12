import type { PropsWithChildren } from 'react';
import { usePage } from '@inertiajs/react';

import { PublicSiteFooter } from '@/components/public-site-footer';
import { PublicSiteHeader } from '@/components/public-site-header';

export default function PublicLayout({ children }: PropsWithChildren) {
    const { localization } = usePage().props;

    return (
        <div className="min-h-screen bg-background text-foreground antialiased">
            <a
                href="#main-content"
                className="fixed top-3 left-3 z-50 -translate-y-20 rounded-md bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-sm transition-transform focus:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                {localization.copy.skipToMainContent}
            </a>
            <PublicSiteHeader />
            {children}
            <PublicSiteFooter />
        </div>
    );
}
