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
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
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
    const { routeContext } = usePage().props;

    if (!routeContext) {
        return null;
    }

    const unread = notifications.filter(
        (notification) => !notification.readAt,
    ).length;

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
                    <div className="max-w-2xl">
                        <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                            Live programme activity
                        </p>
                        <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            Notifications
                        </h1>
                        <p className="mt-3 text-sm leading-6 text-[#c7d6dd] sm:text-base">
                            Assessment, evidence, funding, and access events
                            update in real time through the secured notification
                            channel.
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
                                    Mark all read
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
                                Activity inbox
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {unread} unread on this page ·{' '}
                                {pagination.total} matching
                            </p>
                        </div>
                        <BellRing
                            className="size-5 text-[#147a55]"
                            aria-hidden="true"
                        />
                    </div>

                    {notifications.length > 0 ? (
                        <div className="divide-y divide-border">
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
                                                {notification.category}
                                            </Badge>
                                            {!notification.readAt && (
                                                <Badge className="bg-[#147a55] text-white">
                                                    New
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            {notification.message}
                                        </p>
                                        {notification.createdAt && (
                                            <time className="mt-2 block text-xs text-muted-foreground">
                                                {new Intl.DateTimeFormat(
                                                    DEFAULT_LOCALE,
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
                                                    Open
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
                                                    >
                                                        Mark read
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="px-6 py-16 text-center">
                            <Bell
                                className="mx-auto size-8 text-muted-foreground/60"
                                aria-hidden="true"
                            />
                            <h2 className="mt-4 font-bold text-foreground">
                                You are all caught up
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                New activity within your role and county scope
                                will appear here.
                            </p>
                        </div>
                    )}
                    {pagination.lastPage > 1 && (
                        <div className="border-t px-5 py-4">
                            <Pagination>
                                <PaginationContent>
                                    <PaginationItem>
                                        <PaginationPrevious
                                            href={`?page=${Math.max(1, pagination.currentPage - 1)}`}
                                            aria-disabled={
                                                pagination.currentPage === 1
                                            }
                                        />
                                    </PaginationItem>
                                    <PaginationItem>
                                        <PaginationNext
                                            href={`?page=${Math.min(pagination.lastPage, pagination.currentPage + 1)}`}
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
