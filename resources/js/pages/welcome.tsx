import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    BookOpen,
    Building2,
    MessageSquareText,
    ShieldCheck,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { dashboard, login } from '@/routes';
import { index as citizenEngagement } from '@/routes/citizen-engagement';
import { verify as verifyCertificate } from '@/routes/learning/certificates';

export default function Welcome() {
    const { auth, currentTeam, localization } = usePage().props;
    const copy = localization.welcome;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : login();
    const workAreas = [
        {
            icon: Building2,
            title: copy.delivery_coordination,
            detail: copy.delivery_coordination_detail,
        },
        {
            icon: BarChart3,
            title: copy.performance_assurance,
            detail: copy.performance_assurance_detail,
        },
        {
            icon: BookOpen,
            title: copy.knowledge_capability,
            detail: copy.knowledge_capability_detail,
        },
        {
            icon: MessageSquareText,
            title: copy.citizen_service,
            detail: copy.citizen_service_detail,
        },
    ];

    return (
        <>
            <Head title={copy.head_title}>
                <meta name="description" content={copy.meta_description} />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="border-b bg-background">
                        <div className="mx-auto grid max-w-360 lg:grid-cols-[minmax(0,1.08fr)_minmax(28rem,.92fr)]">
                            <div className="flex min-h-144 flex-col justify-center px-5 py-14 sm:px-8 lg:min-h-164 lg:px-14 lg:py-20 xl:px-20">
                                <div className="max-w-3xl">
                                    <p className="flex items-center gap-2 text-sm font-semibold text-primary">
                                        <ShieldCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {copy.service_label}
                                    </p>
                                    <h1 className="mt-6 max-w-[14ch] text-[clamp(2.75rem,5.6vw,5.25rem)] leading-[1.02] font-semibold tracking-[-0.035em] text-balance text-foreground">
                                        {copy.hero_title}
                                    </h1>
                                    <p className="mt-7 max-w-2xl text-lg leading-8 text-pretty text-muted-foreground">
                                        {copy.hero_description}
                                    </p>
                                    <div className="mt-9 flex flex-wrap items-center gap-4">
                                        <Button asChild size="lg">
                                            <Link
                                                href={
                                                    auth.user
                                                        ? dashboardUrl
                                                        : login()
                                                }
                                                prefetch
                                            >
                                                {auth.user
                                                    ? copy.continue_dashboard
                                                    : copy.sign_in}
                                                <ArrowRight
                                                    data-icon="inline-end"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </Button>
                                        <p className="max-w-sm text-xs leading-5 text-muted-foreground">
                                            {copy.authorized_access}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-primary px-5 py-12 text-primary-foreground sm:px-8 lg:px-10 lg:py-16">
                                <div className="mx-auto flex h-full max-w-2xl flex-col justify-center">
                                    <div className="border-b border-primary-foreground/20 pb-7">
                                        <p className="text-xl font-semibold">
                                            {copy.operating_scope}
                                        </p>
                                        <p className="mt-2 max-w-xl text-sm leading-6 text-primary-foreground/75">
                                            {copy.operating_scope_description}
                                        </p>
                                    </div>
                                    <ul className="divide-y divide-primary-foreground/15">
                                        {workAreas.map((area) => (
                                            <li
                                                key={area.title}
                                                className="grid grid-cols-[1.25rem_1fr] gap-4 py-6"
                                            >
                                                <area.icon
                                                    className="mt-0.5 size-5"
                                                    aria-hidden="true"
                                                />
                                                <div>
                                                    <h2 className="font-semibold">
                                                        {area.title}
                                                    </h2>
                                                    <p className="mt-2 max-w-xl text-sm leading-6 text-primary-foreground/70">
                                                        {area.detail}
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        className="bg-muted/45"
                        aria-labelledby="public-services-title"
                    >
                        <div className="mx-auto max-w-360 px-5 py-14 sm:px-8 lg:px-10 lg:py-18">
                            <div className="grid gap-10 lg:grid-cols-[minmax(15rem,.65fr)_minmax(0,1.35fr)] lg:gap-16">
                                <div>
                                    <h2
                                        id="public-services-title"
                                        className="text-3xl font-semibold tracking-tight text-foreground"
                                    >
                                        {copy.public_services_title}
                                    </h2>
                                    <p className="mt-3 max-w-sm text-sm leading-6 text-muted-foreground">
                                        {copy.public_services_description}
                                    </p>
                                </div>

                                <div className="divide-y border-y">
                                    <PublicService
                                        href={citizenEngagement()}
                                        title={copy.citizen_feedback_title}
                                        description={
                                            copy.citizen_feedback_description
                                        }
                                        action={copy.citizen_feedback_action}
                                    />
                                    <PublicService
                                        href={verifyCertificate()}
                                        title={copy.certificate_title}
                                        description={
                                            copy.certificate_description
                                        }
                                        action={copy.certificate_action}
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-t bg-background">
                        <div className="mx-auto flex max-w-360 flex-col gap-4 px-5 py-9 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                            <div className="flex items-start gap-3">
                                <ShieldCheck
                                    className="mt-0.5 size-5 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2 className="font-semibold text-foreground">
                                        {copy.platform_standard}
                                    </h2>
                                    <p className="mt-1 max-w-4xl text-sm leading-6 text-muted-foreground">
                                        {copy.platform_standard_description}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </PublicLayout>
        </>
    );
}

function PublicService({
    href,
    title,
    description,
    action,
}: {
    href: ReturnType<typeof citizenEngagement>;
    title: string;
    description: string;
    action: string;
}) {
    return (
        <Link
            href={href}
            prefetch
            className="group grid gap-4 py-7 transition-colors hover:bg-background/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-4"
        >
            <div>
                <h3 className="text-lg font-semibold text-foreground">
                    {title}
                </h3>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
            <span className="inline-flex items-center gap-2 text-sm font-semibold text-primary">
                {action}
                <ArrowUpRight
                    className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                    aria-hidden="true"
                />
            </span>
        </Link>
    );
}
