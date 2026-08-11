import type { PropsWithChildren } from 'react';

import { PublicSiteFooter } from '@/components/public-site-footer';
import { PublicSiteHeader } from '@/components/public-site-header';

export default function PublicLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-[#f4f6f4] text-[#14202a] antialiased dark:bg-[#0b1720] dark:text-[#edf4f0]">
            <a
                href="#main-content"
                className="fixed top-3 left-3 z-50 -translate-y-20 rounded-md bg-white px-4 py-2 text-sm font-semibold text-[#12304a] shadow-sm transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-2"
            >
                Skip to main content
            </a>
            <PublicSiteHeader />
            {children}
            <PublicSiteFooter />
        </div>
    );
}
