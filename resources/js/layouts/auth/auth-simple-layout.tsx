import { Link } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import KenyaFlag from '@/components/kenya-flag';
import { faqs, help, home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-[#f4f6f4] lg:grid-cols-[minmax(25rem,.82fr)_minmax(32rem,1.18fr)] dark:bg-[#0b1720]">
            <a
                href="#main-content"
                className="fixed top-3 left-3 z-50 -translate-y-20 rounded-md bg-white px-4 py-2 text-sm font-semibold text-[#12304a] shadow-sm transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-2"
            >
                Skip to main content
            </a>
            <aside className="relative hidden overflow-hidden bg-[#12304a] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
                <div
                    className="absolute inset-x-0 top-0 flex h-1"
                    aria-hidden="true"
                >
                    <span className="flex-1 bg-black" />
                    <span className="flex-1 bg-[#bb0000]" />
                    <span className="flex-1 bg-[#006600]" />
                </div>

                <Link
                    href={home()}
                    className="relative z-10 inline-flex w-fit items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-4 focus-visible:ring-offset-[#12304a]"
                >
                    <AppLogoIcon className="size-11 text-white" />
                    <span className="border-l border-white/25 pl-3">
                        <span className="block text-lg leading-none font-bold">
                            IDMIS
                        </span>
                        <span className="mt-1 block text-xs text-white/65">
                            State Department for Devolution
                        </span>
                    </span>
                </Link>

                <div className="relative z-10 max-w-lg py-14">
                    <div className="flex items-center gap-3 text-sm font-medium text-white/75">
                        <KenyaFlag className="h-6 w-9 shadow-[0_1px_2px_rgb(0_0_0/0.25)]" />
                        Republic of Kenya
                    </div>
                    <p className="mt-7 text-4xl leading-[1.08] font-semibold tracking-[-0.035em] text-balance">
                        One view.
                        <br />
                        Every county.
                    </p>
                    <p className="mt-6 max-w-md text-base leading-7 text-white/70">
                        Secure coordination, performance monitoring, learning,
                        and accountability for Kenya’s devolved system of
                        government.
                    </p>
                </div>

                <div className="relative z-10 flex items-center justify-between gap-6 border-t border-white/15 pt-6 text-xs text-white/60">
                    <span className="inline-flex items-center gap-2">
                        <LockKeyhole
                            className="size-4 text-[#78c7a4]"
                            aria-hidden="true"
                        />
                        Authorized access only
                    </span>
                    <a
                        href="https://www.devolution.go.ke"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="rounded-sm underline-offset-4 hover:text-white hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    >
                        devolution.go.ke
                    </a>
                </div>
            </aside>

            <main
                id="main-content"
                tabIndex={-1}
                className="flex min-h-svh flex-col focus:outline-none"
            >
                <div className="flex items-center justify-between gap-4 border-b border-[#dce3df] bg-white px-5 py-4 sm:px-8 lg:hidden dark:border-white/10 dark:bg-[#0f2230]">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa]"
                    >
                        <AppLogoIcon className="size-9 text-[#12304a] dark:text-white" />
                        <span className="font-bold text-[#12304a] dark:text-white">
                            IDMIS
                        </span>
                    </Link>
                    <span className="flex items-center gap-2 text-xs font-medium text-[#52636f] dark:text-[#aebfc7]">
                        <KenyaFlag className="h-5 w-8" />
                        Republic of Kenya
                    </span>
                </div>

                <div className="flex flex-1 items-center justify-center px-6 py-12 sm:px-10">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <h1 className="text-2xl font-semibold tracking-[-0.025em] text-[#12304a] dark:text-white">
                                {title}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-[#52636f] dark:text-[#aebfc7]">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>

                <nav
                    aria-label="Authentication help"
                    className="flex justify-center gap-5 px-6 pb-8 text-xs text-[#52636f] dark:text-[#aebfc7]"
                >
                    <Link
                        href={help()}
                        className="rounded-sm hover:text-[#12304a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:hover:text-white"
                    >
                        Help
                    </Link>
                    <Link
                        href={faqs()}
                        className="rounded-sm hover:text-[#12304a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:hover:text-white"
                    >
                        FAQs
                    </Link>
                    <span>State Department for Devolution</span>
                </nav>
            </main>
        </div>
    );
}
