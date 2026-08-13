import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, ShieldCheck } from 'lucide-react';
import PublicLayout from '@/layouts/public-layout';
import { index as citizenEngagement } from '@/routes/citizen-engagement';
import { index as dataRights } from '@/routes/data-rights';

type Props = {
    notice: {
        version: string;
        issuedOn: string;
        approvalStatus: string;
        copy: Record<string, string>;
    };
};

const sectionKeys = [
    'controller',
    'data',
    'purpose',
    'sharing',
    'retention',
    'security',
    'rights',
    'choices',
    'contact',
    'legal',
] as const;
const informationEmail = 'info@devolution.go.ke';

export default function PrivacyNotice({ notice }: Props) {
    const { copy } = notice;

    return (
        <PublicLayout>
            <Head title={copy.page_title} />
            <main id="main-content" tabIndex={-1}>
                <div className="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-16">
                    <Link
                        href={citizenEngagement()}
                        className="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <ArrowLeft aria-hidden="true" />
                        {copy.back_to_case}
                    </Link>
                    <header className="mt-8 border-b pb-8">
                        <p className="text-sm font-semibold tracking-[0.14em] text-primary uppercase">
                            {copy.eyebrow}
                        </p>
                        <h1 className="mt-3 max-w-4xl text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
                            {copy.title}
                        </h1>
                        <p className="mt-5 max-w-3xl text-lg leading-8 text-muted-foreground">
                            {copy.summary}
                        </p>
                        <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {copy.version}
                                    {':'}
                                </strong>{' '}
                                {notice.version}
                            </span>
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {copy.issued_on}
                                    {':'}
                                </strong>{' '}
                                {notice.issuedOn}
                            </span>
                        </div>
                    </header>
                    {notice.approvalStatus ===
                        'draft_pending_dpo_legal_approval' && (
                        <aside className="mt-8 flex gap-3 border-l-4 border-primary bg-muted/60 p-5 text-sm leading-6">
                            <ShieldCheck
                                className="mt-0.5 size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <p>{copy.draft_notice}</p>
                        </aside>
                    )}
                    <div className="mt-10 divide-y border-y">
                        {sectionKeys.map((key) => (
                            <section
                                key={key}
                                aria-labelledby={`${key}-heading`}
                                className="grid gap-3 py-7 md:grid-cols-[15rem_1fr] md:gap-10"
                            >
                                <h2
                                    id={`${key}-heading`}
                                    className="text-lg font-semibold text-foreground"
                                >
                                    {copy[`${key}_title`]}
                                </h2>
                                <div className="text-base leading-7 text-muted-foreground">
                                    <p>{copy[`${key}_body`]}</p>
                                    {key === 'contact' && (
                                        <ul className="mt-4 grid gap-2">
                                            <li>
                                                <a
                                                    href="mailto:info@devolution.go.ke"
                                                    className="font-medium text-foreground underline underline-offset-4"
                                                >
                                                    {copy.department_contact}
                                                    {': '}
                                                    {informationEmail}
                                                </a>
                                            </li>
                                            <li>
                                                <a
                                                    href="https://www.odpc.go.ke/rights-of-a-data-subject/"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-4"
                                                >
                                                    {copy.odpc_contact}
                                                    <ExternalLink
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </a>
                                            </li>
                                        </ul>
                                    )}
                                </div>
                            </section>
                        ))}
                    </div>
                    <div className="mt-8">
                        <Link
                            href={dataRights()}
                            className="inline-flex min-h-11 items-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {copy.exercise_rights}
                        </Link>
                    </div>
                </div>
            </main>
        </PublicLayout>
    );
}
