import { Head, Link } from '@inertiajs/react';
import {
    ExternalLink,
    KeyRound,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
} from 'lucide-react';

import PublicLayout from '@/layouts/public-layout';
import { faqs, login } from '@/routes';
import { request } from '@/routes/password';

const focusClass =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

export default function Help() {
    return (
        <>
            <Head title="Help and support">
                <meta
                    name="description"
                    content="IDMIS access guidance and official State Department for Devolution contact details."
                />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="border-b bg-background">
                        <div className="mx-auto max-w-360 px-5 py-14 sm:px-8 sm:py-18 lg:px-10">
                            <p className="text-sm font-semibold text-primary">
                                Support and contact
                            </p>
                            <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-[-0.035em] text-balance text-foreground sm:text-5xl">
                                Get help with IDMIS
                            </h1>
                            <p className="mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                                Choose the route that matches your issue. Never
                                share your password, recovery codes, or one-time
                                verification code with support personnel.
                            </p>
                        </div>
                    </section>

                    <section className="mx-auto grid max-w-360 gap-12 px-5 py-12 sm:px-8 lg:grid-cols-[1fr_.85fr] lg:px-10 lg:py-16">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight text-foreground">
                                System access
                            </h2>
                            <div className="mt-6 divide-y border-y">
                                <div className="grid gap-4 py-6 sm:grid-cols-[2.75rem_1fr]">
                                    <KeyRound
                                        className="size-6 text-primary"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            I already have an account
                                        </h3>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            Sign in with your approved work
                                            email. If you have forgotten your
                                            password, request a secure reset
                                            link.
                                        </p>
                                        <div className="mt-4 flex flex-wrap gap-3">
                                            <Link
                                                href={login()}
                                                className={`inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 ${focusClass}`}
                                            >
                                                Sign in
                                            </Link>
                                            <Link
                                                href={request()}
                                                className={`inline-flex min-h-10 items-center rounded-md border px-4 text-sm font-semibold text-foreground hover:bg-accent ${focusClass}`}
                                            >
                                                Reset password
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                                <div className="grid gap-4 py-6 sm:grid-cols-[2.75rem_1fr]">
                                    <ShieldCheck
                                        className="size-6 text-primary"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            I need access
                                        </h3>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            IDMIS does not allow public
                                            registration. Ask your designated
                                            county, national, verification, or
                                            programme administrator to grant the
                                            role required for your official
                                            duties.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href={faqs()}
                                className={`mt-8 inline-flex rounded-sm text-sm font-semibold text-primary underline-offset-4 hover:underline ${focusClass}`}
                            >
                                Browse frequently asked questions
                            </Link>
                        </div>

                        <aside
                            className="bg-primary p-7 text-primary-foreground sm:p-9"
                            aria-labelledby="department-contact"
                        >
                            <h2
                                id="department-contact"
                                className="text-2xl font-semibold tracking-[-0.025em]"
                            >
                                Department enquiries
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-white/70">
                                For official enquiries and feedback, contact the
                                State Department for Devolution using its
                                published channels.
                            </p>
                            <address className="mt-8 not-italic">
                                <ul className="grid gap-5 text-sm">
                                    <li className="flex gap-3">
                                        <MapPin
                                            className="mt-0.5 size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            Teleposta Towers, Kenyatta Avenue
                                            <br />
                                            P.O. Box 30004–00100
                                            <br />
                                            Nairobi, Kenya
                                        </span>
                                    </li>
                                    <li>
                                        <a
                                            href="tel:+254202250645"
                                            className={`inline-flex items-center gap-3 hover:underline ${focusClass}`}
                                        >
                                            <Phone
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            +254 020 225 0645
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="mailto:info@devolution.go.ke"
                                            className={`inline-flex items-center gap-3 hover:underline ${focusClass}`}
                                        >
                                            <Mail
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            info@devolution.go.ke
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="mailto:complaints@devolution.go.ke"
                                            className={`inline-flex items-center gap-3 hover:underline ${focusClass}`}
                                        >
                                            <Mail
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                            complaints@devolution.go.ke
                                        </a>
                                    </li>
                                </ul>
                            </address>
                            <a
                                href="https://www.devolution.go.ke/contact-us"
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`mt-9 inline-flex min-h-11 items-center gap-2 rounded-md bg-primary-foreground px-4 text-sm font-semibold text-primary hover:bg-primary-foreground/90 ${focusClass}`}
                            >
                                Official contact page
                                <ExternalLink
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </aside>
                    </section>
                </main>
            </PublicLayout>
        </>
    );
}
