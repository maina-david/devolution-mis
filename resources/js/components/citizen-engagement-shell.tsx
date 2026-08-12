import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { LocaleMenu } from '@/components/locale-menu';
import { faqs, help, home } from '@/routes';
import { index as citizenEngagementIndex } from '@/routes/citizen-engagement';

export default function CitizenEngagementShell({
    children,
}: {
    children: ReactNode;
}) {
    const { localization } = usePage().props;
    const { copy } = localization;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:ring-2 focus:ring-ring"
            >
                {copy.skipToMainContent}
            </a>
            <header className="border-b bg-background">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link href={home()} aria-label={`IDMIS ${copy.home}`}>
                        <AppLogo />
                    </Link>
                    <nav
                        aria-label={copy.publicNavigation}
                        className="flex flex-wrap items-center gap-4 text-sm"
                    >
                        <Link
                            href={citizenEngagementIndex()}
                            className="font-medium"
                        >
                            {copy.citizenEngagement}
                        </Link>
                        <Link href={faqs()}>{copy.faqs}</Link>
                        <Link href={help()}>{copy.help}</Link>
                        <LocaleMenu />
                    </nav>
                </div>
            </header>
            <main id="main-content" tabIndex={-1}>
                {children}
            </main>
            <footer className="mt-16 border-t">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p>
                        {copy.departmentName} · {copy.systemName}
                    </p>
                    <div className="flex flex-wrap gap-4">
                        <a
                            href="https://devolution.go.ke"
                            target="_blank"
                            rel="noreferrer"
                        >
                            {copy.departmentWebsite}
                        </a>
                        <Link href={help()}>{copy.accessibilitySupport}</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
