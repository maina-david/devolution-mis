import { Link } from '@inertiajs/react';
import { ExternalLink, Mail, MapPin, Phone } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import { faqs, help, home, login } from '@/routes';
import { verify as verifyCertificate } from '@/routes/learning/certificates';

const linkClass =
    'rounded-sm text-sm text-[#52636f] underline-offset-4 hover:text-[#12304a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:text-[#aebfc7] dark:hover:text-white';

export function PublicSiteFooter() {
    return (
        <footer className="border-t border-[#dce3df] bg-white dark:border-white/10 dark:bg-[#0f2230]">
            <div className="mx-auto grid max-w-[90rem] gap-10 px-6 py-12 sm:px-10 md:grid-cols-2 lg:grid-cols-[1.2fr_.8fr_1fr] lg:px-12">
                <div>
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa]"
                    >
                        <AppLogoIcon className="size-9 text-[#12304a] dark:text-white" />
                        <span>
                            <span className="block font-bold text-[#12304a] dark:text-white">
                                IDMIS
                            </span>
                            <span className="block text-xs text-[#52636f] dark:text-[#aebfc7]">
                                Integrated Devolution Management Information
                                System
                            </span>
                        </span>
                    </Link>
                    <p className="mt-5 max-w-md text-sm leading-6 text-[#52636f] dark:text-[#aebfc7]">
                        Secure access is granted by authorized administrators to
                        national, county, verification, and programme teams.
                    </p>
                </div>

                <div>
                    <h2 className="text-sm font-semibold text-[#12304a] dark:text-white">
                        System links
                    </h2>
                    <ul className="mt-4 grid gap-3">
                        <li>
                            <Link href={login()} className={linkClass}>
                                Sign in
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={verifyCertificate()}
                                className={linkClass}
                            >
                                Verify learning certificate
                            </Link>
                        </li>
                        <li>
                            <Link href={help()} className={linkClass}>
                                Help and support
                            </Link>
                        </li>
                        <li>
                            <Link href={faqs()} className={linkClass}>
                                Frequently asked questions
                            </Link>
                        </li>
                        <li>
                            <a
                                href="https://www.devolution.go.ke"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`${linkClass} inline-flex items-center gap-1.5`}
                            >
                                State Department website
                                <ExternalLink
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </a>
                        </li>
                    </ul>
                </div>

                <address className="not-italic">
                    <h2 className="text-sm font-semibold text-[#12304a] dark:text-white">
                        State Department for Devolution
                    </h2>
                    <ul className="mt-4 grid gap-3 text-sm leading-6 text-[#52636f] dark:text-[#aebfc7]">
                        <li className="flex gap-2.5">
                            <MapPin
                                className="mt-1 size-4 shrink-0 text-[#147a55] dark:text-[#78c7a4]"
                                aria-hidden="true"
                            />
                            <span>
                                Teleposta Towers, Kenyatta Avenue
                                <br />
                                P.O. Box 30004–00100, Nairobi
                            </span>
                        </li>
                        <li>
                            <a
                                href="tel:+254202250645"
                                className={`${linkClass} inline-flex items-center gap-2.5`}
                            >
                                <Phone
                                    className="size-4 text-[#147a55] dark:text-[#78c7a4]"
                                    aria-hidden="true"
                                />
                                +254 020 225 0645
                            </a>
                        </li>
                        <li>
                            <a
                                href="mailto:info@devolution.go.ke"
                                className={`${linkClass} inline-flex items-center gap-2.5`}
                            >
                                <Mail
                                    className="size-4 text-[#147a55] dark:text-[#78c7a4]"
                                    aria-hidden="true"
                                />
                                info@devolution.go.ke
                            </a>
                        </li>
                    </ul>
                </address>
            </div>
            <div className="border-t border-[#dce3df] dark:border-white/10">
                <div className="mx-auto flex max-w-[90rem] flex-col gap-2 px-6 py-5 text-xs text-[#657680] sm:flex-row sm:justify-between sm:px-10 lg:px-12 dark:text-[#8fa3ad]">
                    <p>
                        © {new Date().getFullYear()} State Department for
                        Devolution
                    </p>
                    <a
                        href="mailto:complaints@devolution.go.ke"
                        className="rounded-sm hover:text-[#12304a] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:hover:text-white"
                    >
                        Complaints: complaints@devolution.go.ke
                    </a>
                </div>
            </div>
        </footer>
    );
}
