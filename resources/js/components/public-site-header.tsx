import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import KenyaFlag from '@/components/kenya-flag';
import { LocaleMenu } from '@/components/locale-menu';
import { Button } from '@/components/ui/button';
import { dashboard, faqs, help, home, login } from '@/routes';
import { index as citizenEngagement } from '@/routes/citizen-engagement';
import { verify as verifyCertificate } from '@/routes/learning/certificates';

const navigationLink =
    'inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export function PublicSiteHeader() {
    const { auth, currentTeam, localization } = usePage().props;
    const { copy } = localization;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : login();

    return (
        <header className="sticky top-0 isolate z-40 border-b bg-background">
            <div className="bg-primary text-primary-foreground">
                <div className="mx-auto flex min-h-9 max-w-360 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                    <div className="flex items-center gap-2 text-xs font-medium">
                        <KenyaFlag className="h-3.5 w-5" />
                        <span>{copy.republic}</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <a
                            href="https://www.devolution.go.ke"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="hidden min-h-9 items-center gap-1.5 rounded-sm px-2 text-xs font-medium text-primary-foreground/85 hover:text-primary-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none sm:inline-flex"
                        >
                            {copy.departmentWebsite}
                            <ExternalLink
                                className="size-3"
                                aria-hidden="true"
                            />
                        </a>
                        <LocaleMenu inverse />
                    </div>
                </div>
            </div>

            <div className="mx-auto flex min-h-18 max-w-360 items-center gap-5 px-4 sm:px-6 lg:px-10">
                <Link
                    href={home()}
                    className="flex min-w-0 items-center gap-3 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 focus-visible:outline-none"
                    aria-label={`IDMIS ${copy.home}`}
                >
                    <AppLogoIcon className="size-11 shrink-0" />
                    <span className="min-w-0">
                        <span className="block text-lg leading-none font-bold tracking-tight text-foreground">
                            IDMIS
                        </span>
                        <span className="mt-1 hidden truncate text-xs font-medium text-muted-foreground sm:block">
                            {copy.departmentName}
                        </span>
                    </span>
                </Link>

                <nav
                    aria-label={copy.primaryNavigation}
                    className="ml-auto hidden items-center gap-1 md:flex"
                >
                    <Link href={citizenEngagement()} className={navigationLink}>
                        {copy.citizenEngagement}
                    </Link>
                    <Link href={verifyCertificate()} className={navigationLink}>
                        {copy.verifyCertificate}
                    </Link>
                    <Link href={faqs()} className={navigationLink}>
                        {copy.faqs}
                    </Link>
                    <Link href={help()} className={navigationLink}>
                        {copy.help}
                    </Link>
                </nav>

                <Button asChild className="ml-auto shrink-0 md:ml-2">
                    <Link href={auth.user ? dashboardUrl : login()} prefetch>
                        {auth.user ? copy.dashboard : copy.signIn}
                        <ArrowRight data-icon="inline-end" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </header>
    );
}
