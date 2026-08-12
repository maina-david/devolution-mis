import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, House, LifeBuoy } from 'lucide-react';
import KenyaFlag from '@/components/kenya-flag';
import { Button } from '@/components/ui/button';
import { dashboard, help, home } from '@/routes';

type ErrorPageProps = {
    status: number;
    title: string;
    description: string;
    goBackLabel: string;
};

export default function ErrorPage({ status, title, description, goBackLabel }: ErrorPageProps) {
    const { auth, localization } = usePage().props;
    const copy = localization.copy;
    const destination = auth?.user ? dashboard() : home();

    return (
        <>
            <Head title={`${status} — ${title}`} />
            <a
                href="#main-content"
                className="sr-only fixed top-3 left-3 z-50 rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only"
            >
                {copy.skipToMainContent}
            </a>
            <main
                id="main-content"
                tabIndex={-1}
                className="flex min-h-screen flex-col bg-background text-foreground outline-none"
            >
                <header className="border-b border-primary/20 bg-primary text-primary-foreground">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-5 px-5 py-4 sm:px-8">
                        <Link href={home()} className="flex items-center gap-3">
                            <img
                                src="/images/branding/devolution-emblem.png"
                                alt={copy.republic}
                                className="h-14 w-20 rounded bg-white object-contain p-1"
                            />
                            <span>
                                <strong className="block text-lg leading-tight">IDMIS</strong>
                                <span className="block text-xs text-primary-foreground/80">
                                    {copy.departmentName}
                                </span>
                            </span>
                        </Link>
                        <KenyaFlag className="h-7 w-10 shrink-0 shadow-sm" />
                    </div>
                </header>

                <section className="mx-auto flex w-full max-w-6xl flex-1 items-center px-5 py-14 sm:px-8">
                    <div className="grid w-full items-center gap-10 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div className="max-w-2xl">
                            <p className="font-mono text-sm font-semibold text-primary">
                                HTTP {status}
                            </p>
                            <h1 className="mt-4 text-balance text-4xl font-semibold tracking-[-0.035em] sm:text-5xl">
                                {title}
                            </h1>
                            <p className="mt-5 max-w-[65ch] text-pretty text-base leading-7 text-muted-foreground sm:text-lg">
                                {description}
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Button asChild>
                                    <Link href={destination}>
                                        <House aria-hidden="true" />
                                        {auth?.user ? copy.dashboard : copy.home}
                                    </Link>
                                </Button>
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {goBackLabel}
                                </Button>
                                <Button asChild variant="ghost">
                                    <a href={help().url} target="_blank" rel="noreferrer">
                                        <LifeBuoy aria-hidden="true" />
                                        {copy.help}
                                    </a>
                                </Button>
                            </div>
                        </div>

                        <div className="border-t border-border pt-7 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                            <p className="text-sm font-semibold">{copy.authorizedAccessOnly}</p>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {copy.protectCredentialsDescription}
                            </p>
                        </div>
                    </div>
                </section>

                <footer className="border-t border-border px-5 py-5 text-sm text-muted-foreground sm:px-8">
                    <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2">
                        <span>{copy.systemName}</span>
                        <span>{copy.republic}</span>
                    </div>
                </footer>
            </main>
        </>
    );
}
