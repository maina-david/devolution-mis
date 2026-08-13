import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Bell, BellRing, CheckCheck } from 'lucide-react';
import DateRangeFilter from '@/components/date-range-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { read, readAll } from '@/routes/notifications';

type ProgrammeNotification = {
    id: string;
    title: string;
    message: string;
    category: string;
    url: string | null;
    readAt: string | null;
    createdAt: string | null;
};

export default function NotificationIndex({
    notifications,
    pagination,
    filters,
}: {
    notifications: ProgrammeNotification[];
    pagination: { currentPage: number; lastPage: number; total: number };
    filters: { from?: string; to?: string; search?: string };
}) {
    const page = usePage();
    const { localization } = page.props;
    const copy = localization.notifications;
    const unread = notifications.filter(
        (notification) => !notification.readAt,
    ).length;

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                    <div className="max-w-2xl">
                        <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                            {copy.eyebrow}
                        </p>
                        <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            {copy.title}
                        </h1>
                        <p className="mt-3 text-sm leading-6 text-[#c7d6dd] sm:text-base">
                            {copy.description}
                        </p>
                    </div>
                    {unread > 0 && (
                        <Form {...readAll.form()}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    <CheckCheck aria-hidden="true" />
                                    {copy.mark_all_read}
                                </Button>
                            )}
                        </Form>
                    )}
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold text-foreground">
                                {copy.activity_inbox}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.inbox_summary
                                    .replace(
                                        ':unread',
                                        unread.toLocaleString(
                                            localization.current,
                                        ),
                                    )
                                    .replace(
                                        ':total',
                                        pagination.total.toLocaleString(
                                            localization.current,
                                        ),
                                    )}
                            </p>
                        </div>
                        <BellRing
                            className="size-5 text-[#147a55]"
                            aria-hidden="true"
                        />
                    </div>

                    {notifications.length > 0 ? (
                        <div
                            className="divide-y divide-border"
                            aria-live="polite"
                        >
                            {notifications.map((notification) => (
                                <article
                                    key={notification.id}
                                    className={`flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:px-6 ${notification.readAt ? '' : 'bg-[#147a55]/5'}`}
                                >
                                    <span
                                        className={`mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full ${notification.readAt ? 'bg-muted text-muted-foreground' : 'bg-[#147a55] text-white'}`}
                                    >
                                        <Bell
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-bold text-foreground">
                                                {notification.title}
                                            </h3>
                                            <Badge variant="outline">
                                                {copy[
                                                    notification.category.replaceAll(
                                                        '-',
                                                        '_',
                                                    )
                                                ] ?? notification.category}
                                            </Badge>
                                            {!notification.readAt && (
                                                <Badge className="bg-[#147a55] text-white">
                                                    {copy.new}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            {notification.message}
                                        </p>
                                        {notification.createdAt && (
                                            <time className="mt-2 block text-xs text-muted-foreground">
                                                {new Intl.DateTimeFormat(
                                                    localization.current,
                                                    {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    },
                                                ).format(
                                                    new Date(
                                                        notification.createdAt,
                                                    ),
                                                )}
                                            </time>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        {notification.url && (
                                            <Button variant="outline" asChild>
                                                <Link href={notification.url}>
                                                    {copy.open}
                                                </Link>
                                            </Button>
                                        )}
                                        {!notification.readAt && (
                                            <Form
                                                {...read.form({
                                                    notification:
                                                        notification.id,
                                                })}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        disabled={processing}
                                                        aria-busy={processing}
                                                    >
                                                        {copy.mark_read}
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.empty_title}
                            description={copy.empty_description}
                            className="min-h-64"
                        />
                    )}
                    {pagination.lastPage > 1 && (
                        <div className="border-t px-5 py-4">
                            <Pagination>
                                <PaginationContent>
                                    <PaginationItem>
                                        <PaginationPrevious
                                            href={pageHref(
                                                page.url,
                                                Math.max(
                                                    1,
                                                    pagination.currentPage - 1,
                                                ),
                                            )}
                                            aria-disabled={
                                                pagination.currentPage === 1
                                            }
                                        />
                                    </PaginationItem>
                                    <PaginationItem>
                                        <PaginationNext
                                            href={pageHref(
                                                page.url,
                                                Math.min(
                                                    pagination.lastPage,
                                                    pagination.currentPage + 1,
                                                ),
                                            )}
                                            aria-disabled={
                                                pagination.currentPage ===
                                                pagination.lastPage
                                            }
                                        />
                                    </PaginationItem>
                                </PaginationContent>
                            </Pagination>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function pageHref(currentUrl: string, targetPage: number): string {
    const [path, query = ''] = currentUrl.split('?');
    const parameters = new URLSearchParams(query);
    parameters.set('page', String(targetPage));

    return `${path}?${parameters.toString()}`;
}
