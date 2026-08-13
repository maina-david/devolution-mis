import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart3, Download, MoreHorizontal } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceDataTable from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { interpolate } from '@/hooks/use-localization';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { formatDateTime } from '@/lib/reference-catalog';
import { show as showCounty } from '@/routes/counties';
import { index as knowledgeIndex } from '@/routes/knowledge';
import {
    exportMethod,
    index as analyticsIndex,
} from '@/routes/knowledge/community-analytics';

type Filters = {
    from?: string | null;
    to?: string | null;
    county_id?: string | null;
    status?: string | null;
    search?: string | null;
};

type DiscussionMetric = {
    id: string;
    title: string;
    county: CountyIdentityValue | null;
    visibility: string;
    status: string;
    contributions: number;
    contributors: number;
    subscriptions: number;
    reports: number;
    openReports: number;
    resolutionRate: number;
    lastActivityAt: string | null;
};

type CountyMetric = {
    county: CountyIdentityValue;
    discussions: number;
    contributions: number;
    contributors: number;
    subscriptions: number;
    reports: number;
    openReports: number;
    resolutionRate: number;
};

type Report = {
    summary: {
        discussions: number;
        openDiscussions: number;
        contributions: number;
        contributors: number;
        subscriptions: number;
        reports: number;
        openReports: number;
        resolutionRate: number;
    };
    trend: Array<{ period: string; contributions: number }>;
    discussions: {
        rows: DiscussionMetric[];
        pagination: WorkspacePagination;
    };
    counties: { rows: CountyMetric[]; pagination: WorkspacePagination };
    options: { counties: CountyIdentityValue[] };
};

export default function CommunityAnalytics({
    report,
    filters,
}: {
    report: Report;
    filters: Filters;
}) {
    const page = usePage();
    const { localization } = page.props;
    const copy = localization.knowledge.ui;
    const locale = localization.current;
    const chartConfig = {
        contributions: {
            label: copy.visible_contributions,
            color: 'var(--primary)',
        },
    } satisfies ChartConfig;
    const query = {
        from: filters.from || undefined,
        to: filters.to || undefined,
        county_id: filters.county_id || undefined,
        status: filters.status || undefined,
        search: filters.search || undefined,
    };
    const discussionRows: WorkspaceRow[] = report.discussions.rows.map(
        (item) => ({
            id: item.id,
            status: item.status,
            cells: [
                item.title,
                item.county ?? copy.national,
                item.contributions,
                item.contributors,
                item.subscriptions,
                item.reports,
                item.openReports,
                new Intl.NumberFormat(locale, {
                    style: 'percent',
                    maximumFractionDigits: 1,
                }).format(item.resolutionRate / 100),
                item.lastActivityAt
                    ? formatDateTime(item.lastActivityAt, {
                          dateStyle: 'medium',
                      })
                    : '—',
                copy[item.status] ?? item.status,
            ],
        }),
    );
    const countyRows: WorkspaceRow[] = report.counties.rows.map((item) => ({
        id: item.county.id,
        cells: [
            item.county,
            item.discussions,
            item.contributions,
            item.contributors,
            item.subscriptions,
            item.reports,
            item.openReports,
            new Intl.NumberFormat(locale, {
                style: 'percent',
                maximumFractionDigits: 1,
            }).format(item.resolutionRate / 100),
        ],
        href: preserveDrilldownFilters(
            showCounty.url({ county: item.county.id }),
            page.url,
        ),
    }));

    return (
        <>
            <Head title={copy.community_analytics_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-sm font-medium opacity-80">
                                {copy.knowledge_management}
                            </p>
                            <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.community_health}
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                {copy.community_health_description}
                            </p>
                        </div>
                        <ExportMenu query={query} />
                    </div>
                </section>

                <DateRangeFilter
                    cycles={[]}
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search ?? ''}
                    searchPlaceholder={copy.search_discussions}
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: copy.county,
                            value: filters.county_id,
                            options: report.options.counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            })),
                        },
                        {
                            key: 'status',
                            label: copy.discussion_status,
                            value: filters.status,
                            options: [
                                { id: 'open', name: copy.open },
                                { id: 'closed', name: copy.closed },
                                { id: 'archived', name: copy.archived },
                            ],
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label={copy.discussions}
                        value={report.summary.discussions}
                    />
                    <Summary
                        label={copy.visible_contributions}
                        value={report.summary.contributions}
                    />
                    <Summary
                        label={copy.subscriptions}
                        value={report.summary.subscriptions}
                    />
                    <Summary
                        label={copy.report_resolution}
                        value={new Intl.NumberFormat(locale, {
                            style: 'percent',
                            maximumFractionDigits: 1,
                        }).format(report.summary.resolutionRate / 100)}
                    />
                </div>

                {report.summary.discussions === 0 ? (
                    <WorkspaceEmptyState
                        title={copy.no_community_activity}
                        description={copy.adjust_analytics_filters}
                        className="min-h-72"
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.contribution_trend}</CardTitle>
                                <CardDescription>
                                    {copy.contribution_trend_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {report.trend.length === 0 ? (
                                    <WorkspaceEmptyState
                                        title={copy.no_contribution_trend}
                                        description={
                                            copy.no_contribution_trend_description
                                        }
                                    />
                                ) : (
                                    <ChartContainer
                                        config={chartConfig}
                                        className="h-72 w-full"
                                    >
                                        <BarChart
                                            data={report.trend}
                                            accessibilityLayer
                                        >
                                            <CartesianGrid vertical={false} />
                                            <XAxis
                                                dataKey="period"
                                                tickLine={false}
                                                axisLine={false}
                                            />
                                            <ChartTooltip
                                                cursor={false}
                                                content={
                                                    <ChartTooltipContent />
                                                }
                                            />
                                            <Bar
                                                dataKey="contributions"
                                                fill="var(--color-contributions)"
                                                radius={6}
                                            />
                                        </BarChart>
                                    </ChartContainer>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.discussion_health}</CardTitle>
                                <CardDescription>
                                    {copy.discussion_health_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        copy.discussion,
                                        copy.county_scope,
                                        copy.contributions,
                                        copy.contributors,
                                        copy.subscriptions,
                                        copy.reports,
                                        copy.open_reports,
                                        copy.resolution,
                                        copy.last_activity,
                                        copy.status,
                                    ]}
                                    rows={discussionRows}
                                    pagination={report.discussions.pagination}
                                    renderActionControl={(row) => (
                                        <DiscussionActions
                                            title={String(row.cells[0])}
                                        />
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {copy.cross_county_portfolio}
                                </CardTitle>
                                <CardDescription>
                                    {copy.cross_county_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        copy.county,
                                        copy.discussions,
                                        copy.contributions,
                                        copy.contributors,
                                        copy.subscriptions,
                                        copy.reports,
                                        copy.open_reports,
                                        copy.resolution,
                                    ]}
                                    rows={countyRows}
                                    pagination={report.counties.pagination}
                                />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function Summary({ label, value }: { label: string; value: string | number }) {
    const locale = usePage().props.localization.current;

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardDescription>{label}</CardDescription>
                <BarChart3 className="size-4 text-primary" aria-hidden="true" />
            </CardHeader>
            <CardContent className="text-3xl font-bold">
                {typeof value === 'number'
                    ? value.toLocaleString(locale)
                    : value}
            </CardContent>
        </Card>
    );
}

function ExportMenu({ query }: { query: Record<string, string | undefined> }) {
    const copy = usePage().props.localization.knowledge.ui;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download data-icon="inline-start" aria-hidden="true" />
                    {copy.export_evidence}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a href={exportMethod.url({ format }, { query })}>
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function DiscussionActions({ title }: { title: string }) {
    const copy = usePage().props.localization.knowledge.ui;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={interpolate(copy.discussion_actions, { title })}
                >
                    <MoreHorizontal aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link
                            href={knowledgeIndex.url({
                                query: { search: title },
                            })}
                        >
                            {copy.open_in_repository}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={analyticsIndex.url()}>
                            {copy.reset_analytics_filters}
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
