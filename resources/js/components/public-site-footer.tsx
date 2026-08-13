import { Link, usePage } from '@inertiajs/react';
import { ExternalLink, Mail, MapPin, Phone } from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import { faqs, help, home, login } from '@/routes';
import { index as citizenEngagement } from '@/routes/citizen-engagement';
import { index as dataRights } from '@/routes/data-rights';
import { verify as verifyCertificate } from '@/routes/learning/certificates';
import { show as privacyNotice } from '@/routes/privacy-notice';

const linkClass =
    'rounded-sm text-sm text-primary-foreground/75 underline-offset-4 transition-colors hover:text-primary-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring';
const productName = 'IDMIS';
const officialPhone = '+254 020 225 0645';
const informationEmail = 'info@devolution.go.ke';
const complaintsEmail = 'complaints@devolution.go.ke';

export function PublicSiteFooter() {
    const { localization } = usePage().props;
    const { copy } = localization;

    return (
        <footer className="bg-primary text-primary-foreground">
            <div className="mx-auto grid max-w-360 gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[1.15fr_.85fr_1fr] lg:px-10 lg:py-14">
                <div>
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-3 rounded-md focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                        aria-label={`IDMIS ${copy.home}`}
                    >
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-white p-1">
                            <AppLogoIcon className="size-10" />
                        </span>
                        <span>
                            <span className="block text-lg leading-none font-bold">
                                {productName}
                            </span>
                            <span className="mt-1 block max-w-xs text-xs leading-5 text-primary-foreground/70">
                                {copy.systemName}
                            </span>
                        </span>
                    </Link>
                    <p className="mt-5 max-w-md text-sm leading-6 text-primary-foreground/75">
                        {copy.authorizedAccessDescription}
                    </p>
                </div>

                <nav aria-labelledby="footer-system-links">
                    <h2
                        id="footer-system-links"
                        className="text-sm font-semibold"
                    >
                        {copy.systemLinks}
                    </h2>
                    <ul className="mt-4 grid gap-3">
                        <li>
                            <Link href={login()} className={linkClass}>
                                {copy.signIn}
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={citizenEngagement()}
                                className={linkClass}
                            >
                                {copy.citizenEngagement}
                            </Link>
                        </li>
                        <li>
                            <Link
                                href={verifyCertificate()}
                                className={linkClass}
                            >
                                {copy.verifyLearningCertificate}
                            </Link>
                        </li>
                        <li>
                            <Link href={help()} className={linkClass}>
                                {copy.helpSupport}
                            </Link>
                        </li>
                        <li>
                            <Link href={faqs()} className={linkClass}>
                                {copy.faqs}
                            </Link>
                        </li>
                        <li>
                            <Link href={privacyNotice()} className={linkClass}>
                                {copy.privacyNotice}
                            </Link>
                        </li>
                        <li>
                            <Link href={dataRights()} className={linkClass}>
                                {copy.dataRights}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <address className="not-italic">
                    <h2 className="text-sm font-semibold">
                        {copy.departmentName}
                    </h2>
                    <ul className="mt-4 grid gap-3 text-sm leading-6 text-primary-foreground/75">
                        <li className="flex gap-2.5">
                            <MapPin
                                className="mt-1 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>{copy.postalAddress}</span>
                        </li>
                        <li>
                            <a
                                href="tel:+254202250645"
                                className={`${linkClass} inline-flex items-center gap-2.5`}
                            >
                                <Phone className="size-4" aria-hidden="true" />
                                {officialPhone}
                            </a>
                        </li>
                        <li>
                            <a
                                href="mailto:info@devolution.go.ke"
                                className={`${linkClass} inline-flex items-center gap-2.5`}
                            >
                                <Mail className="size-4" aria-hidden="true" />
                                {informationEmail}
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://www.devolution.go.ke"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`${linkClass} inline-flex items-center gap-1.5`}
                            >
                                {copy.departmentWebsite}
                                <ExternalLink
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </a>
                        </li>
                    </ul>
                </address>
            </div>

            <div className="border-t border-primary-foreground/15">
                <div className="mx-auto flex max-w-360 flex-col gap-2 px-5 py-5 text-xs text-primary-foreground/65 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                    <p>{copy.copyright}</p>
                    <a
                        href="mailto:complaints@devolution.go.ke"
                        className={linkClass}
                    >
                        {copy.complaints}
                        {': '} {complaintsEmail}
                    </a>
                </div>
            </div>
        </footer>
    );
}
