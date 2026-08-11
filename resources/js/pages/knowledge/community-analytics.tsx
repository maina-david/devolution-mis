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

const chartConfig = {
    contributions: { label: 'Visible contributions', color: 'var(--primary)' },
} satisfies ChartConfig;

export default function CommunityAnalytics({
    report,
    filters,
}: {
    report: Report;
    filters: Filters;
}) {
    const page = usePage();
    const teamSlug = page.props.currentTeam!.slug;
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
                item.county ?? 'National',
                item.contributions,
                item.contributors,
                item.subscriptions,
                item.reports,
                item.openReports,
                `${item.resolutionRate}%`,
                item.lastActivityAt
                    ? formatDateTime(item.lastActivityAt, {
                          dateStyle: 'medium',
                      })
                    : '—',
                item.status,
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
            `${item.resolutionRate}%`,
        ],
        href: preserveDrilldownFilters(
            showCounty.url({ current_team: teamSlug, county: item.county.id }),
            page.url,
        ),
    }));

    return (
        <>
            <Head title="Knowledge community analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-sm font-medium opacity-80">
                                Knowledge Management
                            </p>
                            <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                Community health
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                Engagement, subscriptions and governed
                                moderation outcomes across your authorized
                                county portfolio.
                            </p>
                        </div>
                        <ExportMenu teamSlug={teamSlug} query={query} />
                    </div>
                </section>

                <DateRangeFilter
                    cycles={[]}
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search ?? ''}
                    searchPlaceholder="Search discussions"
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'County',
                            value: filters.county_id,
                            options: report.options.counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            })),
                        },
                        {
                            key: 'status',
                            label: 'Discussion status',
                            value: filters.status,
                            options: [
                                { id: 'open', name: 'Open' },
                                { id: 'closed', name: 'Closed' },
                                { id: 'archived', name: 'Archived' },
                            ],
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Discussions"
                        value={report.summary.discussions}
                    />
                    <Summary
                        label="Visible contributions"
                        value={report.summary.contributions}
                    />
                    <Summary
                        label="Subscriptions"
                        value={report.summary.subscriptions}
                    />
                    <Summary
                        label="Report resolution"
                        value={`${report.summary.resolutionRate}%`}
                    />
                </div>

                {report.summary.discussions === 0 ? (
                    <WorkspaceEmptyState
                        title="No community activity matches"
                        description="Adjust the date, county, status or search filters."
                        className="min-h-72"
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Contribution trend</CardTitle>
                                <CardDescription>
                                    Visible contributions within the selected
                                    activity period. Hidden moderation content
                                    is never included.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {report.trend.length === 0 ? (
                                    <WorkspaceEmptyState
                                        title="No contribution trend"
                                        description="The discussions in scope have no visible contributions in this period."
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
                                <CardTitle>Discussion health</CardTitle>
                                <CardDescription>
                                    Visible engagement and aggregate moderation
                                    outcomes; no hidden post body or report
                                    narrative is exposed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        'Discussion',
                                        'County scope',
                                        'Contributions',
                                        'Contributors',
                                        'Subscriptions',
                                        'Reports',
                                        'Open reports',
                                        'Resolution',
                                        'Last activity',
                                        'Status',
                                    ]}
                                    rows={discussionRows}
                                    pagination={report.discussions.pagination}
                                    renderActionControl={(row) => (
                                        <DiscussionActions
                                            teamSlug={teamSlug}
                                            title={String(row.cells[0])}
                                        />
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Cross-county portfolio</CardTitle>
                                <CardDescription>
                                    County identities drill into complete county
                                    records while preserving the active filters.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        'County',
                                        'Discussions',
                                        'Contributions',
                                        'Contributors',
                                        'Subscriptions',
                                        'Reports',
                                        'Open reports',
                                        'Resolution',
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
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardDescription>{label}</CardDescription>
                <BarChart3 className="size-4 text-primary" aria-hidden="true" />
            </CardHeader>
            <CardContent className="text-3xl font-bold">{value}</CardContent>
        </Card>
    );
}

function ExportMenu({
    teamSlug,
    query,
}: {
    teamSlug: string;
    query: Record<string, string | undefined>;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download data-icon="inline-start" /> Export evidence
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    { current_team: teamSlug, format },
                                    { query },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function DiscussionActions({
    teamSlug,
    title,
}: {
    teamSlug: string;
    title: string;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for ${title}`}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link
                            href={knowledgeIndex.url(teamSlug, {
                                query: { search: title },
                            })}
                        >
                            Open in repository
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={analyticsIndex.url(teamSlug)}>
                            Reset analytics filters
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
