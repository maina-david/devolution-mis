import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ExternalLink,
    FileCheck2,
    FolderSearch,
    KeyRound,
    Mail,
    Map,
    MapPin,
    Phone,
    ShieldCheck,
} from 'lucide-react';

import PublicLayout from '@/layouts/public-layout';
import { faqs, login } from '@/routes';
import { request } from '@/routes/password';

const focusClass =
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';
const officialPhone = '+254 020 225 0645';
const informationEmail = 'info@devolution.go.ke';
const complaintsEmail = 'complaints@devolution.go.ke';

export default function Help() {
    const copy = usePage().props.localization.help;
    const commonTasks = copy.common_tasks as unknown as Array<{
        title: string;
        description: string;
    }>;
    const taskIcons = [FileCheck2, BarChart3, Map, FolderSearch];

    return (
        <>
            <Head title={copy.page_title}>
                <meta name="description" content={copy.meta_description} />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="border-b bg-background">
                        <div className="mx-auto max-w-360 px-5 py-14 sm:px-8 sm:py-18 lg:px-10">
                            <p className="text-sm font-semibold text-primary">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-[-0.035em] text-balance text-foreground sm:text-5xl">
                                {copy.title}
                            </h1>
                            <p className="mt-5 max-w-2xl text-lg leading-8 text-muted-foreground">
                                {copy.description}
                            </p>
                        </div>
                    </section>

                    <section
                        className="border-b bg-muted/30"
                        aria-labelledby="common-tasks-heading"
                    >
                        <div className="mx-auto max-w-360 px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
                            <div className="max-w-3xl">
                                <h2
                                    id="common-tasks-heading"
                                    className="text-2xl font-semibold tracking-tight text-foreground"
                                >
                                    {copy.common_tasks_title}
                                </h2>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {copy.common_tasks_description}
                                </p>
                            </div>

                            <ul className="mt-8 grid gap-x-10 gap-y-8 md:grid-cols-2">
                                {commonTasks.map((task, index) => {
                                    const TaskIcon =
                                        taskIcons[index % taskIcons.length];

                                    return (
                                        <li
                                            key={task.title}
                                            className="grid grid-cols-[2.75rem_1fr] gap-4 border-t pt-5"
                                        >
                                            <TaskIcon
                                                className="size-6 text-primary"
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <h3 className="font-semibold text-foreground">
                                                    {task.title}
                                                </h3>
                                                <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                                    {task.description}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </section>

                    <section className="mx-auto grid max-w-360 gap-12 px-5 py-12 sm:px-8 lg:grid-cols-[1fr_.85fr] lg:px-10 lg:py-16">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight text-foreground">
                                {copy.system_access}
                            </h2>
                            <div className="mt-6 divide-y border-y">
                                <div className="grid gap-4 py-6 sm:grid-cols-[2.75rem_1fr]">
                                    <KeyRound
                                        className="size-6 text-primary"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h3 className="font-semibold text-foreground">
                                            {copy.existing_account}
                                        </h3>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            {copy.existing_account_description}
                                        </p>
                                        <div className="mt-4 flex flex-wrap gap-3">
                                            <Link
                                                href={login()}
                                                className={`inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 ${focusClass}`}
                                            >
                                                {copy.sign_in}
                                            </Link>
                                            <Link
                                                href={request()}
                                                className={`inline-flex min-h-10 items-center rounded-md border px-4 text-sm font-semibold text-foreground hover:bg-accent ${focusClass}`}
                                            >
                                                {copy.reset_password}
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
                                            {copy.need_access}
                                        </h3>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            {copy.need_access_description}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href={faqs()}
                                className={`mt-8 inline-flex rounded-sm text-sm font-semibold text-primary underline-offset-4 hover:underline ${focusClass}`}
                            >
                                {copy.browse_faqs}
                            </Link>

                            <div className="mt-10 border-t pt-7">
                                <h2 className="text-lg font-semibold text-foreground">
                                    {copy.support_request_title}
                                </h2>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                                    {copy.support_request_description}
                                </p>
                            </div>
                        </div>

                        <aside
                            className="bg-primary p-7 text-primary-foreground sm:p-9"
                            aria-labelledby="department-contact"
                        >
                            <h2
                                id="department-contact"
                                className="text-2xl font-semibold tracking-[-0.025em]"
                            >
                                {copy.department_enquiries}
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-white/70">
                                {copy.department_description}
                            </p>
                            <address className="mt-8 not-italic">
                                <ul className="grid gap-5 text-sm">
                                    <li className="flex gap-3">
                                        <MapPin
                                            className="mt-0.5 size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {copy.address_line_one}
                                            <br />
                                            {copy.address_line_two}
                                            <br />
                                            {copy.address_line_three}
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
                                            {officialPhone}
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
                                            {informationEmail}
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
                                            {complaintsEmail}
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
                                {copy.official_contact_page}
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
