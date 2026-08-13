import { Link, usePage } from '@inertiajs/react';
import { LockKeyhole, ShieldCheck } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import KenyaFlag from '@/components/kenya-flag';
import { LocaleMenu } from '@/components/locale-menu';
import { faqs, help, home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const productName = 'IDMIS';

export default function AuthSimpleLayout({
    children,
    name,
    title,
    description,
}: AuthLayoutProps) {
    const { localization } = usePage().props;
    const { copy } = localization;
    const resolvedTitle = name === 'login' ? copy.loginTitle : title;
    const resolvedDescription =
        name === 'login' ? copy.loginDescription : description;

    return (
        <div className="flex min-h-svh flex-col bg-muted/35">
            <a
                href="#main-content"
                className="fixed top-3 left-3 z-50 -translate-y-20 rounded-md bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-sm transition-transform focus:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                {copy.skipToMainContent}
            </a>

            <header className="border-b bg-background">
                <div className="bg-primary text-primary-foreground">
                    <div className="mx-auto flex min-h-9 max-w-360 items-center justify-between gap-4 px-5 sm:px-8">
                        <span className="flex items-center gap-2 text-xs font-medium">
                            <KenyaFlag className="h-3.5 w-5" />
                            {copy.republic}
                        </span>
                        <LocaleMenu inverse />
                    </div>
                </div>
                <div className="mx-auto flex min-h-18 max-w-360 items-center justify-between gap-5 px-5 sm:px-8">
                    <Link
                        href={home()}
                        className="flex min-w-0 items-center gap-3 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:outline-none"
                        aria-label={`IDMIS ${copy.home}`}
                    >
                        <AppLogoIcon className="size-11 shrink-0" />
                        <span>
                            <span className="block text-lg leading-none font-bold text-foreground">
                                {productName}
                            </span>
                            <span className="mt-1 hidden text-xs font-medium text-muted-foreground sm:block">
                                {copy.departmentName}
                            </span>
                        </span>
                    </Link>
                    <span className="hidden items-center gap-2 text-xs font-medium text-muted-foreground sm:flex">
                        <LockKeyhole
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />
                        {copy.authorizedAccessOnly}
                    </span>
                </div>
            </header>

            <main
                id="main-content"
                tabIndex={-1}
                className="flex flex-1 focus-visible:outline-none"
            >
                <div className="mx-auto grid w-full max-w-360 lg:grid-cols-[minmax(0,.8fr)_minmax(28rem,1.2fr)]">
                    <aside className="hidden border-r px-10 py-16 lg:block xl:px-16">
                        <ShieldCheck
                            className="size-7 text-primary"
                            aria-hidden="true"
                        />
                        <h2 className="mt-6 max-w-md text-2xl font-semibold tracking-tight text-foreground">
                            {copy.secureGovernmentAccess}
                        </h2>
                        <p className="mt-4 max-w-md text-sm leading-7 text-muted-foreground">
                            {copy.secureGovernmentAccessDescription}
                        </p>
                        <dl className="mt-10 divide-y border-y">
                            <div className="py-5">
                                <dt className="text-sm font-semibold text-foreground">
                                    {copy.accountProvisioning}
                                </dt>
                                <dd className="mt-1 text-sm leading-6 text-muted-foreground">
                                    {copy.accountProvisioningDescription}
                                </dd>
                            </div>
                            <div className="py-5">
                                <dt className="text-sm font-semibold text-foreground">
                                    {copy.protectCredentials}
                                </dt>
                                <dd className="mt-1 text-sm leading-6 text-muted-foreground">
                                    {copy.protectCredentialsDescription}
                                </dd>
                            </div>
                        </dl>
                    </aside>

                    <section className="flex items-center justify-center bg-background px-5 py-12 sm:px-10 lg:min-h-144 lg:py-16">
                        <div className="w-full max-w-md">
                            <div className="mb-8 border-b pb-6">
                                <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                                    {resolvedTitle}
                                </h1>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {resolvedDescription}
                                </p>
                            </div>
                            {children}
                        </div>
                    </section>
                </div>
            </main>

            <footer className="border-t bg-background">
                <div className="mx-auto flex max-w-360 flex-col gap-3 px-5 py-5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <span>{copy.departmentName}</span>
                    <nav
                        aria-label={copy.authenticationHelp}
                        className="flex gap-5"
                    >
                        <Link
                            href={help()}
                            className="hover:text-foreground hover:underline"
                        >
                            {copy.help}
                        </Link>
                        <Link
                            href={faqs()}
                            className="hover:text-foreground hover:underline"
                        >
                            {copy.faqs}
                        </Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}
