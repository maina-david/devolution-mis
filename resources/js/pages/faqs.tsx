import { Head, Link } from '@inertiajs/react';
import { ChevronDown, ExternalLink } from 'lucide-react';

import PublicLayout from '@/layouts/public-layout';
import { help, login } from '@/routes';

const questions = [
    {
        question: 'What is IDMIS?',
        answer: 'The Integrated Devolution Management Information System is a secure platform for coordinating programmes, monitoring results, managing evidence, sharing learning, and strengthening accountability across national and county governments.',
    },
    {
        question: 'Who can access the system?',
        answer: 'Access is limited to authorized national, county, independent verification, programme, and support personnel. Permissions depend on your organization and assigned responsibilities.',
    },
    {
        question: 'How do I get an account?',
        answer: 'Accounts are created or granted by authorized IDMIS administrators. There is no public self-registration. Contact your designated county or programme administrator if your duties require access.',
    },
    {
        question: 'What should I do if I cannot sign in?',
        answer: 'First confirm that you are using the official email address associated with your account. Use the password-reset option if needed. If the problem continues, contact your designated administrator or use the channels on the Help page.',
    },
    {
        question: 'What information will IDMIS manage?',
        answer: 'The planned scope includes programme and performance information, assessment evidence, reporting documents, decisions, and approved coordination records. Access to every dataset is controlled by role, organization, and purpose.',
    },
    {
        question: 'Can county users see another county’s restricted documents?',
        answer: 'Not by default. Access controls are intended to keep restricted working documents within their approved organization and workflow while allowing authorized national monitoring and approved publication.',
    },
    {
        question: 'Will IDMIS work with slow or interrupted internet?',
        answer: 'The service is being designed for bandwidth-conscious use, including preserved drafts, clear upload progress, and recovery from interrupted document transfers. Exact offline and assisted-support arrangements will follow approved rollout guidance.',
    },
    {
        question: 'Where can I learn more about devolution?',
        answer: 'The State Department for Devolution website publishes policies, plans, guidance, news, and downloadable resources relating to Kenya’s devolved system of government.',
    },
];

export default function Faqs() {
    return (
        <>
            <Head title="Frequently asked questions">
                <meta
                    name="description"
                    content="Answers about IDMIS access, accounts, information, security, and support."
                />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="border-b border-[#dce3df] bg-white dark:border-white/10 dark:bg-[#0f2230]">
                        <div className="mx-auto max-w-5xl px-6 py-14 sm:px-10 sm:py-20">
                            <p className="text-sm font-semibold text-[#147a55] dark:text-[#78c7a4]">
                                IDMIS guidance
                            </p>
                            <h1 className="mt-4 text-4xl font-semibold tracking-[-0.035em] text-balance text-[#12304a] sm:text-5xl dark:text-white">
                                Frequently asked questions
                            </h1>
                            <p className="mt-5 max-w-2xl text-lg leading-8 text-[#52636f] dark:text-[#aebfc7]">
                                Find quick answers about system access,
                                permitted users, information handling, and
                                support.
                            </p>
                        </div>
                    </section>

                    <section className="mx-auto max-w-5xl px-6 py-12 sm:px-10 sm:py-16">
                        <div className="divide-y divide-[#cbd6d0] border-y border-[#cbd6d0] dark:divide-white/15 dark:border-white/15">
                            {questions.map((item, index) => (
                                <details
                                    key={item.question}
                                    className="group py-1"
                                    open={index === 0}
                                >
                                    <summary className="flex min-h-16 cursor-pointer list-none items-center justify-between gap-5 rounded-md px-3 text-base font-semibold text-[#12304a] hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:text-white dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                                        {item.question}
                                        <ChevronDown
                                            className="size-5 shrink-0 text-[#147a55] transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none dark:text-[#78c7a4]"
                                            aria-hidden="true"
                                        />
                                    </summary>
                                    <p className="max-w-3xl px-3 pb-6 text-sm leading-7 text-[#52636f] dark:text-[#aebfc7]">
                                        {item.answer}
                                    </p>
                                </details>
                            ))}
                        </div>

                        <div className="mt-12 flex flex-col items-start justify-between gap-6 border-t border-[#cbd6d0] pt-8 sm:flex-row sm:items-center dark:border-white/15">
                            <div>
                                <h2 className="text-lg font-semibold text-[#12304a] dark:text-white">
                                    Still need help?
                                </h2>
                                <p className="mt-1 text-sm text-[#52636f] dark:text-[#aebfc7]">
                                    See the support channels or return to sign
                                    in.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={help()}
                                    className="inline-flex min-h-11 items-center rounded-md border border-[#9eada6] px-4 text-sm font-semibold text-[#12304a] hover:border-[#147a55] hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:border-white/25 dark:text-white dark:hover:bg-white/5"
                                >
                                    Help and support
                                </Link>
                                <Link
                                    href={login()}
                                    className="inline-flex min-h-11 items-center rounded-md bg-[#147a55] px-4 text-sm font-semibold text-white hover:bg-[#0d6143] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-2 dark:bg-[#57b58e] dark:text-[#092019]"
                                >
                                    Sign in
                                </Link>
                            </div>
                        </div>

                        <a
                            href="https://www.devolution.go.ke/faqs"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-8 inline-flex items-center gap-2 rounded-sm text-sm font-semibold text-[#147a55] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] dark:text-[#78c7a4]"
                        >
                            Read general devolution FAQs
                            <ExternalLink
                                className="size-4"
                                aria-hidden="true"
                            />
                        </a>
                    </section>
                </main>
            </PublicLayout>
        </>
    );
}
