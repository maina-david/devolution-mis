import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Building2,
    MessageSquareText,
    ShieldCheck,
} from 'lucide-react';

import PublicLayout from '@/layouts/public-layout';
import { dashboard, login } from '@/routes';

function CoordinationMap() {
    return (
        <div className="relative isolate min-h-120 overflow-hidden bg-[#12304a] px-6 py-8 text-white sm:px-10 lg:min-h-full lg:px-12 lg:py-11">
            <svg
                className="absolute inset-0 h-full w-full opacity-35"
                viewBox="0 0 720 720"
                fill="none"
                aria-hidden="true"
                preserveAspectRatio="xMidYMid slice"
            >
                <path
                    d="M-90 501C73 447 125 515 259 456s245-25 341-91c80-55 146-42 221-21M-83 554c156-63 246 32 373-34 117-60 202-15 309-83 77-49 147-44 219-22M-68 607c175-71 269 26 403-47 110-59 199-15 288-72 78-51 135-46 203-28"
                    stroke="#b8d3c6"
                    strokeWidth="1.2"
                />
                <path
                    d="M145 112c83-39 161-28 224 18 82 61 89 142 178 181 64 28 127 22 212-9M82 155c91-31 169-8 220 46 68 72 70 143 156 187 82 41 174 27 277-12"
                    stroke="#64859a"
                    strokeWidth="1"
                />
            </svg>

            <div className="relative flex h-full min-h-104 flex-col">
                <div className="flex items-start justify-between gap-6">
                    <div>
                        <p className="text-sm font-medium text-[#b7d9c9]">
                            Shared delivery picture
                        </p>
                        <p className="mt-1 text-xs text-white/60">
                            National · County · Partner
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white/85">
                        <span className="size-1.5 rounded-full bg-[#65c89e]" />
                        Connected
                    </span>
                </div>

                <div className="my-auto py-10">
                    <svg
                        className="mx-auto w-full max-w-124"
                        viewBox="0 0 520 300"
                        fill="none"
                        aria-label="National, county, and partner information connected through IDMIS"
                    >
                        <g stroke="#8aabba" strokeWidth="1.5">
                            <path d="M260 150 98 72M260 150 72 180M260 150l-97 90M260 150l160-84M260 150l190 91M260 150l47 111" />
                            <path
                                d="M98 72 72 180l91 60M420 66l30 175-143 20"
                                opacity=".4"
                            />
                        </g>
                        <g fill="#1f4b68" stroke="#9ebdca" strokeWidth="2">
                            <circle cx="98" cy="72" r="13" />
                            <circle cx="72" cy="180" r="10" />
                            <circle cx="163" cy="240" r="12" />
                            <circle cx="420" cy="66" r="12" />
                            <circle cx="450" cy="241" r="14" />
                            <circle cx="307" cy="261" r="10" />
                        </g>
                        <circle cx="260" cy="150" r="58" fill="#f7f3e8" />
                        <circle cx="260" cy="150" r="42" fill="#147a55" />
                        <path
                            d="M242 128v44m0-44h8c15 0 24 8 24 22s-9 22-24 22h-8m20-22 12-15 12 15 10-15v37"
                            stroke="white"
                            strokeWidth="4"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        <g fill="#d6a43b">
                            <circle cx="98" cy="72" r="5" />
                            <circle cx="450" cy="241" r="5" />
                        </g>
                        <g fill="#65c89e">
                            <circle cx="72" cy="180" r="4" />
                            <circle cx="163" cy="240" r="4" />
                            <circle cx="420" cy="66" r="4" />
                            <circle cx="307" cy="261" r="4" />
                        </g>
                    </svg>
                </div>

                <div className="flex items-end justify-between gap-8 border-t border-white/15 pt-5">
                    <div>
                        <p className="text-2xl font-semibold tracking-[-0.03em]">
                            47
                        </p>
                        <p className="mt-1 text-xs text-white/60">
                            Counties, one framework
                        </p>
                    </div>
                    <p className="max-w-60 text-right text-xs leading-5 text-white/65">
                        Governed information for coordination, learning, and
                        accountability.
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function Welcome() {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : login();

    return (
        <>
            <Head title="Integrated Devolution Management Information System">
                <meta
                    name="description"
                    content="A shared platform for coordinating, monitoring, and learning from devolution programmes across Kenya."
                />
            </Head>

            <PublicLayout>
                <main id="main-content" tabIndex={-1}>
                    <section className="mx-auto grid max-w-360 border-x border-[#dce3df] bg-white lg:grid-cols-[minmax(0,1.08fr)_minmax(28rem,.92fr)] dark:border-white/10 dark:bg-[#0f2230]">
                        <div className="flex min-h-152 flex-col justify-between px-6 py-12 sm:px-10 sm:py-16 lg:min-h-176 lg:px-16 lg:py-20 xl:px-20">
                            <div className="max-w-3xl">
                                <p className="inline-flex items-center gap-2 text-sm font-semibold text-[#147a55] dark:text-[#78c7a4]">
                                    <span className="size-2 rounded-full bg-[#c8902f]" />
                                    Integrated public-sector coordination
                                </p>
                                <h1 className="mt-8 max-w-[13ch] text-[clamp(3rem,6.4vw,5.75rem)] leading-[0.98] font-semibold tracking-[-0.04em] text-balance text-[#12304a] dark:text-white">
                                    One view. Every county.
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-pretty text-[#40525f] dark:text-[#bdcbd1]">
                                    IDMIS brings national and county teams
                                    together to coordinate programmes, monitor
                                    results, share learning, and strengthen
                                    accountability.
                                </p>

                                <div className="mt-10 flex">
                                    <Link
                                        href={
                                            auth.user ? dashboardUrl : login()
                                        }
                                        prefetch
                                        className="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-[#147a55] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#0d6143] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1769aa] focus-visible:ring-offset-2 dark:bg-[#57b58e] dark:text-[#092019] dark:hover:bg-[#74c7a5]"
                                    >
                                        {auth.user
                                            ? 'Continue to dashboard'
                                            : 'Sign in to IDMIS'}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </div>
                            </div>

                            <div className="mt-16 flex items-center gap-3 border-t border-[#dce3df] pt-6 text-sm text-[#52636f] dark:border-white/10 dark:text-[#aebfc7]">
                                <ShieldCheck
                                    className="size-5 text-[#147a55] dark:text-[#78c7a4]"
                                    aria-hidden="true"
                                />
                                <span>
                                    Secure access for authorized national,
                                    county, and programme teams.
                                </span>
                            </div>
                        </div>

                        <CoordinationMap />
                    </section>

                    <section
                        aria-labelledby="platform-purpose"
                        className="border-y border-[#dce3df] bg-[#edf2ef] dark:border-white/10 dark:bg-[#0b1720]"
                    >
                        <div className="mx-auto max-w-360 px-6 py-12 sm:px-10 lg:px-12 lg:py-14">
                            <div className="grid gap-10 lg:grid-cols-[minmax(16rem,.65fr)_minmax(0,1.35fr)] lg:gap-16">
                                <div>
                                    <h2
                                        id="platform-purpose"
                                        className="max-w-[16ch] text-2xl leading-tight font-semibold tracking-tight text-[#12304a] dark:text-white"
                                    >
                                        A shared operating picture for
                                        devolution delivery
                                    </h2>
                                    <p className="mt-4 max-w-136 text-sm leading-6 text-[#52636f] dark:text-[#aebfc7]">
                                        Designed to make progress visible,
                                        decisions traceable, and collaboration
                                        easier across institutions.
                                    </p>
                                </div>

                                <div className="divide-y divide-[#cbd6d0] border-y border-[#cbd6d0] dark:divide-white/15 dark:border-white/15">
                                    {[
                                        {
                                            icon: Building2,
                                            title: 'Coordinate delivery',
                                            description:
                                                'Align programmes, milestones, responsibilities, and follow-up across national and county teams.',
                                        },
                                        {
                                            icon: BarChart3,
                                            title: 'Monitor results',
                                            description:
                                                'Track agreed indicators, surface delivery risks, and support evidence-based action.',
                                        },
                                        {
                                            icon: MessageSquareText,
                                            title: 'Learn and account',
                                            description:
                                                'Preserve decisions, share lessons, and close the loop with partners and citizens.',
                                        },
                                    ].map((item) => (
                                        <div
                                            key={item.title}
                                            className="grid gap-3 py-6 sm:grid-cols-[3rem_12rem_1fr] sm:items-start sm:gap-5"
                                        >
                                            <span className="flex size-10 items-center justify-center rounded-md bg-white text-[#147a55] shadow-[0_1px_2px_rgb(18_48_74/0.08)] dark:bg-white/8 dark:text-[#78c7a4]">
                                                <item.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <h3 className="pt-1.5 text-sm font-semibold text-[#12304a] dark:text-white">
                                                {item.title}
                                            </h3>
                                            <p className="text-sm leading-6 text-[#52636f] dark:text-[#aebfc7]">
                                                {item.description}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </PublicLayout>
        </>
    );
}
