import type { ReactNode } from 'react';

import PublicLayout from '@/layouts/public-layout';

export default function CitizenEngagementShell({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <PublicLayout>
            <main id="main-content" tabIndex={-1}>
                {children}
            </main>
        </PublicLayout>
    );
}
