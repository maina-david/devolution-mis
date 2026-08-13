import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink } from 'lucide-react';

import PublicLayout from '@/layouts/public-layout';
import { help, login } from '@/routes';

export default function Faqs() {
    const copy = usePage().props.localization.support;
    const questions = copy.questions as Array<{
        question: string;
        answer: string;
    }>;

    return (
        <>
            <Head title={copy.faqs_title}>
                <meta name="description" content={copy.faqs_meta} />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="border-b bg-background">
                        <div className="mx-auto max-w-5xl px-5 py-14 sm:px-8 sm:py-18">
                            <p className="text-sm font-semibold text-primary">
                                {copy.guidance}
                            </p>
                            <h1 className="mt-4 text-4xl font-semibold tracking-[-0.035em] text-balance text-foreground sm:text-5xl">
                                {copy.faqs_title}
                            </h1>
                            <p className="mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                                {copy.faqs_intro}
                            </p>
                        </div>
                    </section>

                    <section className="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-16">
                        <div className="divide-y border-y">
                            {questions.map((item, index) => (
                                <details
                                    key={item.question}
                                    className="group py-1"
                                    open={index === 0}
                                >
                                    <summary className="flex min-h-16 cursor-pointer list-none items-center justify-between gap-5 rounded-md px-3 text-base font-semibold text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                                        {item.question}
                                        <ChevronDown
                                            className="size-5 shrink-0 text-primary transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none"
                                            aria-hidden="true"
                                        />
                                    </summary>
                                    <p className="max-w-3xl px-3 pb-6 text-sm leading-7 text-muted-foreground">
                                        {item.answer}
                                    </p>
                                </details>
                            ))}
                        </div>

                        <div className="mt-12 flex flex-col items-start justify-between gap-6 border-t pt-8 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="text-lg font-semibold text-foreground">
                                    {copy.still_need_help}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {copy.support_or_sign_in}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Link
                                    href={help()}
                                    className="inline-flex min-h-11 items-center rounded-md border px-4 text-sm font-semibold text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {copy.help_and_support}
                                </Link>
                                <Link
                                    href={login()}
                                    className="inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    {copy.sign_in}
                                </Link>
                            </div>
                        </div>

                        <a
                            href="https://www.devolution.go.ke/faqs"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-8 inline-flex items-center gap-2 rounded-sm text-sm font-semibold text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {copy.general_faqs}
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
