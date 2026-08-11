import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import KenyaFlag from '@/components/kenya-flag';
import { dashboard, faqs, help, home, login } from '@/routes';
import { verify as verifyCertificate } from '@/routes/learning/certificates';

export function PublicSiteHeader() {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : login();

    return (
        <header className="border-b border-[#dce3df] bg-white dark:border-white/10 dark:bg-[#0f2230]">
            <div className="mx-auto flex min-h-20 max-w-[90rem] items-center justify-between gap-5 px-5 sm:px-8 lg:px-12">
                <Link
                    href={home()}
                    className="flex items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-4 dark:focus-visible:ring-offset-[#0f2230]"
                    aria-label="IDMIS home"
                >
                    <AppLogoIcon className="size-10 text-[#12304a] dark:text-white" />
                    <span className="border-l border-[#d7dfdb] pl-3 dark:border-white/20">
                        <span className="block text-lg leading-none font-bold tracking-[-0.02em] text-[#12304a] dark:text-white">
                            IDMIS
                        </span>
                        <span className="mt-1 hidden text-[0.68rem] leading-none font-medium text-[#52636f] sm:block dark:text-[#aebfc7]">
                            State Department for Devolution
                        </span>
                    </span>
                    <span className="hidden items-center gap-2 border-l border-[#d7dfdb] pl-3 lg:flex dark:border-white/20">
                        <KenyaFlag className="h-4 w-6 rounded-[1px] shadow-sm" />
                        <span className="text-[0.62rem] leading-tight font-semibold tracking-[0.08em] text-[#52636f] uppercase dark:text-[#aebfc7]">
                            Republic of Kenya
                        </span>
                    </span>
                </Link>

                <nav
                    aria-label="Primary navigation"
                    className="flex items-center gap-1 sm:gap-2"
                >
                    <Link
                        href={verifyCertificate()}
                        prefetch
                        className="hidden min-h-11 items-center rounded-md px-3 text-sm font-semibold text-[#40525f] transition-colors hover:bg-[#edf2ef] hover:text-[#12304a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] xl:inline-flex dark:text-[#bdcbd1] dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        Verify certificate
                    </Link>
                    <Link
                        href={faqs()}
                        prefetch
                        className="hidden min-h-11 items-center rounded-md px-3 text-sm font-semibold text-[#40525f] transition-colors hover:bg-[#edf2ef] hover:text-[#12304a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] sm:inline-flex dark:text-[#bdcbd1] dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        FAQs
                    </Link>
                    <Link
                        href={help()}
                        prefetch
                        className="hidden min-h-11 items-center rounded-md px-3 text-sm font-semibold text-[#40525f] transition-colors hover:bg-[#edf2ef] hover:text-[#12304a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] md:inline-flex dark:text-[#bdcbd1] dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        Help
                    </Link>
                    <a
                        href="https://www.devolution.go.ke"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="hidden min-h-11 items-center gap-1.5 rounded-md px-3 text-sm font-semibold text-[#40525f] transition-colors hover:bg-[#edf2ef] hover:text-[#12304a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] lg:inline-flex dark:text-[#bdcbd1] dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        Department website
                        <ExternalLink className="size-3.5" aria-hidden="true" />
                    </a>
                    <Link
                        href={auth.user ? dashboardUrl : login()}
                        prefetch
                        className="inline-flex min-h-11 items-center gap-2 rounded-md bg-[#147a55] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#0d6143] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-2 dark:bg-[#57b58e] dark:text-[#092019] dark:hover:bg-[#74c7a5]"
                    >
                        {auth.user ? 'Dashboard' : 'Sign in'}
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </nav>
            </div>
        </header>
    );
}
