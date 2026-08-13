import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { Activity, Clock3, MonitorDot, Users } from 'lucide-react';
import DateRangeFilter from '@/components/date-range-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { WorkspaceRow } from '@/components/workspace-data-table';
import WorkspaceDataTable from '@/components/workspace-data-table';
import { interpolate } from '@/hooks/use-localization';
import { index as userActivityIndex } from '@/routes/user-activity';

type ActivitySession = {
    id: string;
    user: { id: string; name: string; email: string; role: string | null };
    currentRoute: string | null;
    currentPath: string | null;
    currentPageTitle: string | null;
    lastMethod: string | null;
    lastAction: string | null;
    ipAddress: string | null;
    loggedInAt: string;
    lastSeenAt: string;
    loggedOutAt: string | null;
};

type AuditEvent = {
    id: string;
    actor: string;
    action: string;
    description: string;
    route: string | null;
    method: string | null;
    ipAddress: string | null;
    occurredAt: string | null;
    sessionId: string | null;
};

type PageView = {
    id: string;
    user: string;
    pageTitle: string;
    route: string;
    path: string;
    ipAddress: string | null;
    viewedAt: string;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

const formatDateTime = (
    value: string | null,
    locale: string,
    activeLabel: string,
) =>
    value
        ? new Intl.DateTimeFormat(locale, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : activeLabel;

export default function UserActivityIndex({
    activeSessions,
    sessions,
    events,
    pageViews,
    users,
    filters,
    onlineWindowMinutes,
}: {
    activeSessions: ActivitySession[];
    sessions: Paginator<ActivitySession>;
    events: Paginator<AuditEvent>;
    pageViews: Paginator<PageView>;
    users: Array<{ id: string; name: string }>;
    filters: {
        userId?: string | null;
        sessionId?: string | null;
        search?: string;
        from?: string | null;
        to?: string | null;
    };
    onlineWindowMinutes: number;
}) {
    const { localization } = usePage().props;
    const copy = localization.userActivity;
    const locale = localization.current;
    const number = (value: number) =>
        new Intl.NumberFormat(locale).format(value);
    const onlineUsers = new Set(
        activeSessions.map((session) => session.user.id),
    ).size;
    const roleLabel = (role: string | null) =>
        role
            ? (copy[`role_${role.replaceAll('-', '_')}`] ?? role)
            : copy.no_role;

    usePoll(15000, {
        only: ['activeSessions', 'sessions', 'events', 'pageViews'],
    });

    const openUser = (userId: string, sessionId?: string) =>
        router.get(
            userActivityIndex.url(),
            { ...filters, user_id: userId, session_id: sessionId },
            { preserveState: true, preserveScroll: true },
        );
    const pagination = (page: Paginator<unknown>, pageName: string) => ({
        currentPage: page.current_page,
        lastPage: page.last_page,
        perPage: page.per_page,
        total: page.total,
        pageName,
    });
    const sessionRows: WorkspaceRow[] = sessions.data.map((session) => ({
        id: session.id,
        status: session.loggedOutAt ? 'closed' : 'active',
        cells: [
            session.user.name,
            roleLabel(session.user.role),
            formatDateTime(session.loggedInAt, locale, copy.active),
            formatDateTime(session.lastSeenAt, locale, copy.active),
            formatDateTime(session.loggedOutAt, locale, copy.active),
            session.currentPageTitle ??
                session.currentRoute ??
                copy.no_page_recorded,
            session.ipAddress ?? '—',
            session.loggedOutAt ? copy.closed : copy.active,
        ],
        meta: { userId: session.user.id },
    }));
    const eventRows: WorkspaceRow[] = events.data.map((event) => ({
        id: event.id,
        cells: [
            event.actor,
            event.action,
            event.description,
            event.route ?? '—',
            event.method ?? '—',
            event.ipAddress ?? '—',
            formatDateTime(event.occurredAt, locale, copy.active),
        ],
        meta: { sessionId: event.sessionId },
    }));
    const pageViewRows: WorkspaceRow[] = pageViews.data.map((view) => ({
        id: view.id,
        cells: [
            view.user,
            view.pageTitle,
            view.route,
            view.path,
            view.ipAddress ?? '—',
            formatDateTime(view.viewedAt, locale, copy.active),
        ],
    }));

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <p className="sr-only" role="status" aria-live="polite">
                    {interpolate(copy.live_region, {
                        users: number(onlineUsers),
                        sessions: number(activeSessions.length),
                    })}
                </p>
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.eyebrow}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {copy.heading}
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                        {copy.description}
                    </p>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Metric
                        icon={Users}
                        label={copy.users_online}
                        value={number(onlineUsers)}
                        detail={interpolate(copy.seen_window, {
                            minutes: number(onlineWindowMinutes),
                        })}
                    />
                    <Metric
                        icon={MonitorDot}
                        label={copy.active_sessions}
                        value={number(activeSessions.length)}
                        detail={copy.authorized_scope}
                    />
                    <Metric
                        icon={Activity}
                        label={copy.matching_events}
                        value={number(events.total)}
                        detail={
                            filters.userId
                                ? copy.selected_user
                                : copy.all_authorized_users
                        }
                    />
                </div>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'user_id',
                            label: copy.user,
                            options: users,
                            value: filters.userId,
                        },
                    ]}
                    searchPlaceholder={copy.search_placeholder}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MonitorDot className="size-5 text-primary" />
                            {copy.online_now}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {activeSessions.length ? (
                            activeSessions.map((session) => (
                                <Button
                                    key={session.id}
                                    variant="outline"
                                    className="h-auto justify-start p-4 text-left"
                                    onClick={() =>
                                        openUser(session.user.id, session.id)
                                    }
                                >
                                    <span className="min-w-0">
                                        <span className="flex items-center gap-2 font-semibold">
                                            <span className="size-2 rounded-full bg-emerald-500" />
                                            {session.user.name}
                                        </span>
                                        <span className="mt-1 block truncate text-sm text-muted-foreground">
                                            {session.currentPageTitle ??
                                                session.currentRoute ??
                                                copy.authenticated}{' '}
                                            {copy.metadata_separator}{' '}
                                            {formatDateTime(
                                                session.lastSeenAt,
                                                locale,
                                                copy.active,
                                            )}
                                        </span>
                                    </span>
                                </Button>
                            ))
                        ) : (
                            <p className="col-span-full py-8 text-center text-sm text-muted-foreground">
                                {copy.no_active_users}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.login_logout_sessions}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {sessionRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    copy.user,
                                    copy.role,
                                    copy.logged_in,
                                    copy.last_seen,
                                    copy.logged_out,
                                    copy.last_page,
                                    copy.ip_address,
                                    copy.status,
                                ]}
                                rows={sessionRows}
                                pagination={pagination(
                                    sessions,
                                    'session_page',
                                )}
                                getRowHref={(row) =>
                                    userActivityIndex.url({
                                        query: {
                                            ...filters,
                                            user_id: row.meta?.userId,
                                            session_id: row.id,
                                        },
                                    })
                                }
                            />
                        ) : (
                            <EmptyState
                                text={copy.no_sessions}
                                action={copy.adjust_filters}
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.page_access_history}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pageViewRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    copy.user,
                                    copy.page,
                                    copy.route,
                                    copy.path,
                                    copy.ip_address,
                                    copy.viewed,
                                ]}
                                rows={pageViewRows}
                                pagination={pagination(pageViews, 'view_page')}
                            />
                        ) : (
                            <EmptyState
                                text={copy.no_page_views}
                                action={copy.adjust_filters}
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.immutable_timeline}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {eventRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    copy.actor,
                                    copy.action,
                                    copy.description_column,
                                    copy.route,
                                    copy.method,
                                    copy.ip_address,
                                    copy.occurred,
                                ]}
                                rows={eventRows}
                                pagination={pagination(events, 'event_page')}
                            />
                        ) : (
                            <EmptyState
                                text={copy.no_audit_events}
                                action={copy.adjust_filters}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Clock3;
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between p-5">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-foreground">
                        {value}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detail}
                    </p>
                </div>
                <span className="rounded-lg bg-primary/10 p-2 text-primary">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </CardContent>
        </Card>
    );
}

function EmptyState({ text, action }: { text: string; action: string }) {
    return (
        <div className="flex min-h-40 flex-col items-center justify-center gap-2 text-center">
            <Clock3
                className="size-6 text-muted-foreground"
                aria-hidden="true"
            />
            <p className="text-sm text-muted-foreground">{text}</p>
            <Badge variant="outline">{action}</Badge>
        </div>
    );
}
