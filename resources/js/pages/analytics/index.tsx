import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    CalendarClock,
    Download,
    Eye,
    FileCheck2,
    MoreHorizontal,
    Play,
    Plus,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    XAxis,
    YAxis,
} from 'recharts';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import { index as analyticsIndex } from '@/routes/analytics';
import {
    publish,
    store as storeDashboard,
} from '@/routes/analytics/dashboards';
import {
    destroy as destroyFilterView,
    store as storeFilterView,
} from '@/routes/analytics/filter-views';
import { download } from '@/routes/analytics/runs';
import {
    activate,
    run,
    store as storeSchedule,
} from '@/routes/analytics/schedules';
import { store as storeWidget } from '@/routes/analytics/widgets';
import { show as countyShow } from '@/routes/counties';

type Option = { id: string; name: string };
type Measurement = {
    value: number | null;
    unit: string;
    provenance: string;
    measured_at: string;
    series: Array<{ county: CountyIdentityValue; value: number | null }>;
    trend: Array<{ period: string; label: string; value: number | null }>;
};
type Widget = {
    id: string;
    title: string;
    description: string | null;
    metricKey: string;
    visualization: string;
    disaggregation: string | null;
    position: number;
    width: number;
    measurement: Measurement;
};
type Dashboard = {
    id: string;
    code: string;
    name: string;
    description: string;
    county: CountyIdentityValue | null;
    audienceRoles: string[];
    status: string;
    checksum: string | null;
    referenceData: ReferenceData | null;
    publishedAt: string | null;
    creator: string;
    publisher: string | null;
    widgets: Widget[];
};

const analyticsChartConfig = {
    value: {
        label: 'Measured value',
        color: 'var(--primary)',
    },
} satisfies ChartConfig;
type ReportSchedule = {
    id: string;
    code: string;
    name: string;
    county: CountyIdentityValue | null;
    referenceData: ReferenceData | null;
    format: string;
    frequency: string;
    filters: Record<string, string | null>;
    recipientCount: number;
    status: string;
    nextRunAt: string;
    approvedAt: string | null;
    creator: string;
    approver: string | null;
};
type ReferenceData = {
    version: string;
    effectiveFrom: string | null;
    checksum: string;
};
type ReportRun = {
    id: string;
    schedule: { code: string; name: string; format: string };
    status: string;
    periodFrom: string | null;
    periodTo: string | null;
    sizeBytes: number | null;
    sha256: string | null;
    recordCount: number | null;
    errorDetail: string | null;
    startedAt: string | null;
    completedAt: string | null;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    dashboards: Dashboard[];
    schedules: ReportSchedule[];
    runs: PageSet<ReportRun> | null;
    filters: Record<string, string | undefined>;
    savedFilterViews: Array<{
        id: string;
        name: string;
        filters: Record<string, string>;
        isDefault: boolean;
    }>;
    options: {
        counties: CountyIdentityValue[];
        metrics: Option[];
        roles: Option[];
        users: Option[];
        publishedDashboards: Option[];
    };
    catalogue: { available: false } | ({ available: true } & ReferenceData);
    capabilities: {
        manage: boolean;
        approveDashboard: boolean;
        approveSchedule: boolean;
    };
};

export default function AnalyticsReporting({
    dashboards,
    schedules,
    runs,
    filters,
    savedFilterViews,
    options,
    catalogue,
    capabilities,
}: Props) {
    const analyticsCopy = usePage().props.localization.analytics;
    const dashboardOptions = dashboards.map((dashboard) => ({
        id: dashboard.id,
        name: `${dashboard.code} · ${dashboard.name}`,
    }));
    const widgetOptions = dashboards.flatMap((dashboard) =>
        dashboard.widgets.map((widget) => ({
            id: widget.id,
            name: `${dashboard.code} · ${widget.title}`,
        })),
    );
    const runRows: WorkspaceRow[] =
        runs?.data.map((report) => ({
            id: report.id,
            status: report.status,
            cells: [
                `${report.schedule.code} · ${report.schedule.name}`,
                report.schedule.format.toUpperCase(),
                report.periodFrom && report.periodTo
                    ? `${report.periodFrom} – ${report.periodTo}`
                    : 'Configured period',
                report.recordCount ?? '—',
                formatBytes(report.sizeBytes),
                report.sha256?.slice(0, 16) ?? 'Pending',
                report.completedAt ? formatDate(report.completedAt) : 'Pending',
                humanize(report.status),
            ],
        })) ?? [];
    const pagination: WorkspacePagination | null = runs
        ? {
              currentPage: runs.current_page,
              lastPage: runs.last_page,
              perPage: runs.per_page,
              total: runs.total,
              pageName: 'runs_page',
          }
        : null;

    return (
        <>
            <Head title={analyticsCopy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                {analyticsCopy.eyebrow}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {analyticsCopy.title}
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                {analyticsCopy.description}
                            </p>
                        </div>
                        {capabilities.manage && (
                            <div className="flex flex-wrap gap-2">
                                <DashboardForm
                                    options={options}
                                    catalogue={catalogue}
                                />
                                <ScheduleForm
                                    options={options}
                                    catalogue={catalogue}
                                />
                            </div>
                        )}
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title="Dashboards in scope"
                        value={dashboards.length}
                        detail={`${dashboards.filter((item) => item.status === 'published').length} independently published`}
                    />
                    <MetricCard
                        title="Governed widgets"
                        value={dashboards.reduce(
                            (sum, item) => sum + item.widgets.length,
                            0,
                        )}
                        detail="Allowlisted metrics with provenance"
                    />
                    <MetricCard
                        title="Active schedules"
                        value={
                            schedules.filter((item) => item.status === 'active')
                                .length
                        }
                        detail="Maker-checker delivery controls"
                    />
                    <MetricCard
                        title="Generated artifacts"
                        value={runs?.total ?? 0}
                        detail="Private and SHA-256 verified"
                    />
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: 'Publication status',
                            options: ['draft', 'published'].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: 'County scope',
                            options: options.counties,
                            value: filters.county_id,
                        },
                        {
                            key: 'dashboard_id',
                            label: analyticsCopy.dashboard_filter,
                            options: dashboardOptions,
                            value: filters.dashboard_id,
                        },
                        {
                            key: 'widget_id',
                            label: analyticsCopy.widget_filter,
                            options: widgetOptions,
                            value: filters.widget_id,
                        },
                        {
                            key: 'visualization',
                            label: analyticsCopy.visualization,
                            options: [
                                'metric',
                                'bar',
                                'line',
                                'area',
                                'progress',
                                'table',
                            ].map(option),
                            value: filters.visualization,
                        },
                        {
                            key: 'time_grain',
                            label: analyticsCopy.time_grain,
                            options: ['month', 'quarter', 'year'].map((id) => ({
                                id,
                                name: analyticsCopy[id],
                            })),
                            value: filters.time_grain,
                        },
                    ]}
                />

                <SavedFilterViews views={savedFilterViews} filters={filters} />

                <section className="grid gap-5">
                    {dashboards.map((dashboard) => (
                        <DashboardPanel
                            key={dashboard.id}
                            dashboard={dashboard}
                            options={options}
                            capabilities={capabilities}
                            filters={filters}
                        />
                    ))}
                    {dashboards.length === 0 && (
                        <WorkspaceEmptyState
                            title="No analytics dashboards in scope"
                            description="An authorized configurator can create a draft dashboard with governed metrics for independent publication."
                        />
                    )}
                </section>

                {(capabilities.manage || capabilities.approveSchedule) && (
                    <section className="grid gap-4">
                        <div>
                            <h2 className="font-bold">
                                {analyticsCopy.schedules_title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {analyticsCopy.schedules_description}
                            </p>
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {schedules.map((schedule) => (
                                <ScheduleCard
                                    key={schedule.id}
                                    schedule={schedule}
                                    capabilities={capabilities}
                                />
                            ))}
                            {schedules.length === 0 && (
                                <WorkspaceEmptyState
                                    title="No report schedules"
                                    description="Create a delivery schedule against an independently published dashboard."
                                />
                            )}
                        </div>
                    </section>
                )}

                {runs && pagination && (
                    <section className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-bold">
                                {analyticsCopy.runs_title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {analyticsCopy.immutable_runs.replace(
                                    ':count',
                                    runs.total.toLocaleString(),
                                )}
                            </p>
                        </div>
                        {runRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    'Schedule',
                                    'Format',
                                    'Period',
                                    'Metrics',
                                    'Size',
                                    'Checksum',
                                    'Completed',
                                    'Status',
                                ]}
                                rows={runRows}
                                pagination={pagination}
                                renderActionControl={(row) => {
                                    const report = runs.data.find(
                                        (item) => item.id === row.id,
                                    );

                                    return report ? (
                                        <RunAction report={report} />
                                    ) : null;
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No generated artifacts"
                                description="Approved schedules will create private checksummed report files when due or manually queued."
                                className="min-h-64 border-0"
                            />
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function SavedFilterViews({
    views,
    filters,
}: {
    views: Props['savedFilterViews'];
    filters: Props['filters'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>{analyticsCopy.saved_views}</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {analyticsCopy.saved_views_description}
                    </p>
                </div>
                <FormSheet
                    title="Save current filters"
                    description="Name the current analytics context and optionally use it as your default view."
                    triggerLabel="Save view"
                    icon={Bookmark}
                    size="md"
                >
                    <Form {...storeFilterView.form()} className="grid gap-5">
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="filter-view-name">
                                        {analyticsCopy.view_name}
                                    </Label>
                                    <Input
                                        id="filter-view-name"
                                        name="name"
                                        maxLength={100}
                                        required
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    {errors.name && (
                                        <p
                                            className="text-sm text-destructive"
                                            role="alert"
                                        >
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                {Object.entries(filters).map(([key, value]) =>
                                    value ? (
                                        <input
                                            key={key}
                                            type="hidden"
                                            name={`filters[${key}]`}
                                            value={value}
                                        />
                                    ) : null,
                                )}
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox name="is_default" value="1" />
                                    {analyticsCopy.default_view}
                                </label>
                                <Button type="submit" disabled={processing}>
                                    <Bookmark data-icon="inline-start" />
                                    {processing
                                        ? 'Saving…'
                                        : 'Save filter view'}
                                </Button>
                            </>
                        )}
                    </Form>
                </FormSheet>
            </CardHeader>
            <CardContent>
                {views.length ? (
                    <div
                        className="flex flex-wrap gap-2"
                        aria-label="Saved analytics filter views"
                    >
                        {views.map((view) => (
                            <div
                                key={view.id}
                                className="flex items-center rounded-md border bg-background"
                            >
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={analyticsIndex({
                                            query: view.filters,
                                        })}
                                    >
                                        {view.name}
                                        {view.isDefault && (
                                            <Badge variant="secondary">
                                                {analyticsCopy.default}
                                            </Badge>
                                        )}
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Delete saved view ${view.name}`}
                                    onClick={() =>
                                        router.delete(
                                            destroyFilterView.url(view.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <WorkspaceEmptyState
                        title="No saved filter views"
                        description="Apply analytics filters, then save the context for quick reuse."
                        className="min-h-40 border-0"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function DashboardPanel({
    dashboard,
    options,
    capabilities,
    filters,
}: {
    dashboard: Dashboard;
    options: Props['options'];
    capabilities: Props['capabilities'];
    filters: Props['filters'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <div className="flex flex-col justify-between gap-4 border-b p-5 sm:flex-row sm:items-start">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge>{dashboard.code}</Badge>
                        <Badge variant="outline">
                            {humanize(dashboard.status)}
                        </Badge>
                        {dashboard.county && (
                            <Link
                                href={countyShow({
                                    county: dashboard.county.id,
                                })}
                            >
                                <CountyIdentity
                                    county={dashboard.county}
                                    compact
                                />
                            </Link>
                        )}
                    </div>
                    <h2 className="mt-3 text-xl font-bold">{dashboard.name}</h2>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {dashboard.description}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {analyticsCopy.configured_by} {dashboard.creator}
                        {dashboard.publisher
                            ? ` · published by ${dashboard.publisher}`
                            : ''}
                        {dashboard.checksum
                            ? ` · SHA-256 ${dashboard.checksum.slice(0, 16)}…`
                            : ''}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {dashboard.referenceData
                            ? `Catalogue ${dashboard.referenceData.version} · ${dashboard.referenceData.checksum.slice(0, 16)}…`
                            : 'Legacy record · reference-data lineage not pinned'}
                    </p>
                </div>
                <div className="flex gap-2">
                    {capabilities.manage && dashboard.status === 'draft' && (
                        <WidgetForm
                            dashboard={dashboard}
                            metrics={options.metrics}
                        />
                    )}
                    {capabilities.approveDashboard &&
                        dashboard.status === 'draft' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        publish.url({
                                            dashboard: dashboard.id,
                                        }),
                                    )
                                }
                            >
                                <FileCheck2 />
                                {analyticsCopy.publish_independently}
                            </Button>
                        )}
                </div>
            </div>
            <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                {dashboard.widgets.map((widget) => (
                    <WidgetCard
                        key={widget.id}
                        widget={widget}
                        filters={filters}
                    />
                ))}
            </div>
        </section>
    );
}

function WidgetCard({
    widget,
    filters,
}: {
    widget: Widget;
    filters: Props['filters'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;
    const trendData = widget.measurement.trend.map((entry) => ({
        period: entry.label,
        value: entry.value ?? 0,
    }));

    return (
        <Card
            className={
                widget.width === 3
                    ? 'md:col-span-2 xl:col-span-3'
                    : widget.width === 2
                      ? 'md:col-span-2'
                      : ''
            }
        >
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-3 text-base">
                    <span>{widget.title}</span>
                    <Badge variant="outline">
                        {humanize(widget.visualization)}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div>
                    <p className="text-4xl font-bold tracking-tight">
                        {widget.measurement.value === null
                            ? 'No data'
                            : `${widget.measurement.value.toLocaleString()}${widget.measurement.unit === 'percent' ? '%' : ''}`}
                    </p>
                    <p className="mt-2 text-xs leading-5 text-muted-foreground">
                        {widget.measurement.provenance}
                    </p>
                </div>
                {widget.visualization === 'progress' &&
                    widget.measurement.value !== null && (
                        <Progress
                            value={Math.min(100, widget.measurement.value)}
                            aria-label={`${widget.title}: ${widget.measurement.value}${widget.measurement.unit === 'percent' ? '%' : ''}`}
                        />
                    )}
                {widget.visualization === 'bar' &&
                    widget.measurement.series.length > 0 && (
                        <div className="grid gap-2">
                            <ChartContainer
                                config={analyticsChartConfig}
                                className="min-h-64 w-full"
                                role="img"
                                aria-label={`${widget.title} by county`}
                            >
                                <BarChart
                                    accessibilityLayer
                                    data={widget.measurement.series.map(
                                        (entry) => ({
                                            county: entry.county.name,
                                            value: entry.value ?? 0,
                                        }),
                                    )}
                                    margin={{ left: 4, right: 4 }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="county"
                                        tickLine={false}
                                        axisLine={false}
                                        interval="preserveStartEnd"
                                        tickFormatter={(value: string) =>
                                            value.slice(0, 8)
                                        }
                                    />
                                    <YAxis
                                        tickLine={false}
                                        axisLine={false}
                                        width={36}
                                    />
                                    <ChartTooltip
                                        cursor={false}
                                        content={<ChartTooltipContent />}
                                    />
                                    <Bar
                                        dataKey="value"
                                        fill="var(--color-value)"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </div>
                    )}
                {widget.visualization === 'line' && trendData.length > 0 && (
                    <TrendChart
                        kind="line"
                        data={trendData}
                        label={analyticsCopy.trend_chart.replace(
                            ':title',
                            widget.title,
                        )}
                    />
                )}
                {widget.visualization === 'area' && trendData.length > 0 && (
                    <TrendChart
                        kind="area"
                        data={trendData}
                        label={analyticsCopy.trend_chart.replace(
                            ':title',
                            widget.title,
                        )}
                    />
                )}
                {widget.measurement.series.length > 0 && (
                    <div className="grid gap-2">
                        {widget.measurement.series.map((entry) => (
                            <Link
                                key={entry.county.id}
                                href={countyShow(
                                    { county: entry.county.id },
                                    { query: filters },
                                )}
                                className="flex items-center justify-between rounded-lg border p-2 hover:bg-muted/50"
                            >
                                <CountyIdentity county={entry.county} compact />
                                <strong>
                                    {entry.value === null
                                        ? 'No data'
                                        : `${entry.value.toLocaleString()}${widget.measurement.unit === 'percent' ? '%' : ''}`}
                                </strong>
                            </Link>
                        ))}
                    </div>
                )}
                <p className="text-xs text-muted-foreground">
                    {analyticsCopy.measured.replace(
                        ':date',
                        formatDate(widget.measurement.measured_at),
                    )}
                </p>
            </CardContent>
        </Card>
    );
}

function TrendChart({
    kind,
    data,
    label,
}: {
    kind: 'line' | 'area';
    data: Array<{ period: string; value: number }>;
    label: string;
}) {
    const axes = (
        <>
            <CartesianGrid vertical={false} />
            <XAxis
                dataKey="period"
                tickLine={false}
                axisLine={false}
                interval="preserveStartEnd"
            />
            <YAxis tickLine={false} axisLine={false} width={42} />
            <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
        </>
    );

    return (
        <ChartContainer
            config={analyticsChartConfig}
            className="min-h-64 w-full"
            role="img"
            aria-label={label}
        >
            {kind === 'line' ? (
                <LineChart
                    accessibilityLayer
                    data={data}
                    margin={{ left: 4, right: 8 }}
                >
                    {axes}
                    <Line
                        dataKey="value"
                        type="monotone"
                        stroke="var(--color-value)"
                        strokeWidth={2}
                        dot={{ r: 3 }}
                    />
                </LineChart>
            ) : (
                <AreaChart
                    accessibilityLayer
                    data={data}
                    margin={{ left: 4, right: 8 }}
                >
                    {axes}
                    <Area
                        dataKey="value"
                        type="monotone"
                        fill="var(--color-value)"
                        fillOpacity={0.2}
                        stroke="var(--color-value)"
                        strokeWidth={2}
                    />
                </AreaChart>
            )}
        </ChartContainer>
    );
}

function DashboardForm({
    options,
    catalogue,
}: {
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <FormSheet
            title="Create analytics dashboard"
            description="Start a governed draft with an allowlisted metric. A different actor must publish it."
            triggerLabel="New dashboard"
            icon={Plus}
            size="xl"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                !catalogue.available
                    ? 'Publish an approved reference-data catalogue before creating dashboards.'
                    : undefined
            }
        >
            <Form action={storeDashboard()} className="grid gap-5 pt-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                name="code"
                                label="Dashboard code"
                                error={errors.code}
                            />
                            <Field
                                name="name"
                                label="Dashboard name"
                                error={errors.name}
                            />
                            <SearchableSelect
                                id="dashboard-county"
                                name="county_id"
                                label="County scope"
                                options={options.counties}
                                optional
                                error={errors.county_id}
                            />
                            <SearchableMultiSelect
                                name="audience_roles[]"
                                label="Authorized audience roles"
                                options={options.roles}
                                error={errors.audience_roles}
                            />
                        </div>
                        <TextField
                            name="description"
                            label="Purpose and decision supported"
                            error={errors.description}
                        />
                        <div className="grid gap-4 rounded-xl border p-4">
                            <h3 className="font-semibold">
                                {analyticsCopy.first_widget}
                            </h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    name="widgets[0][title]"
                                    label="Widget title"
                                />
                                <SearchableSelect
                                    id="dashboard-metric"
                                    name="widgets[0][metric_key]"
                                    label="Metric"
                                    options={options.metrics}
                                />
                                <SearchableSelect
                                    id="dashboard-visualization"
                                    name="widgets[0][visualization]"
                                    label="Visualization"
                                    options={[
                                        'metric',
                                        'bar',
                                        'line',
                                        'area',
                                        'progress',
                                        'table',
                                    ].map(option)}
                                    defaultValue="metric"
                                />
                                <SearchableSelect
                                    id="dashboard-disaggregation"
                                    name="widgets[0][disaggregation]"
                                    label="Disaggregation"
                                    options={[{ id: 'county', name: 'County' }]}
                                    optional
                                />
                                <SearchableSelect
                                    id="dashboard-time-grain"
                                    name="widgets[0][filters][time_grain]"
                                    label={analyticsCopy.time_grain}
                                    options={['month', 'quarter', 'year'].map(
                                        option,
                                    )}
                                    optional
                                />
                                <input
                                    type="hidden"
                                    name="widgets[0][position]"
                                    value="1"
                                />
                                <input
                                    type="hidden"
                                    name="widgets[0][width]"
                                    value="1"
                                />
                            </div>
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Plus /> {analyticsCopy.save_draft}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function WidgetForm({
    dashboard,
    metrics,
}: {
    dashboard: Dashboard;
    metrics: Option[];
}) {
    const analyticsCopy = usePage().props.localization.analytics;
    const nextPosition =
        Math.max(0, ...dashboard.widgets.map((item) => item.position)) + 1;

    return (
        <FormSheet
            title={`Add widget to ${dashboard.code}`}
            description="Add an allowlisted, provenance-backed metric while the dashboard remains a draft."
            triggerLabel="Add widget"
            icon={BarChart3}
        >
            <Form
                action={storeWidget({ dashboard: dashboard.id })}
                className="grid gap-4 pt-4"
            >
                <Field name="title" label="Widget title" />
                <TextField
                    name="description"
                    label="Interpretation guidance"
                    optional
                />
                <SearchableSelect
                    id={`metric-${dashboard.id}`}
                    name="metric_key"
                    label="Metric"
                    options={metrics}
                />
                <SearchableSelect
                    id={`visual-${dashboard.id}`}
                    name="visualization"
                    label="Visualization"
                    options={[
                        'metric',
                        'bar',
                        'line',
                        'area',
                        'progress',
                        'table',
                    ].map(option)}
                    defaultValue="metric"
                />
                <SearchableSelect
                    id={`disaggregation-${dashboard.id}`}
                    name="disaggregation"
                    label="Disaggregation"
                    options={[{ id: 'county', name: 'County' }]}
                    optional
                />
                <SearchableSelect
                    id={`time-grain-${dashboard.id}`}
                    name="filters[time_grain]"
                    label={analyticsCopy.time_grain}
                    options={['month', 'quarter', 'year'].map(option)}
                    optional
                />
                <SearchableSelect
                    id={`width-${dashboard.id}`}
                    name="width"
                    label="Grid width"
                    options={[
                        { id: '1', name: 'One column' },
                        { id: '2', name: 'Two columns' },
                        { id: '3', name: 'Full row' },
                    ]}
                    defaultValue="1"
                />
                <input type="hidden" name="position" value={nextPosition} />
                <Button type="submit">{analyticsCopy.add_widget}</Button>
            </Form>
        </FormSheet>
    );
}

function ScheduleForm({
    options,
    catalogue,
}: {
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <FormSheet
            title="Create report schedule"
            description="Configure delivery from a published dashboard. Activation requires an independent approver."
            triggerLabel="New schedule"
            icon={CalendarClock}
            size="xl"
            triggerDisabled={
                !catalogue.available || options.publishedDashboards.length === 0
            }
            triggerTitle={
                !catalogue.available
                    ? 'Publish an approved reference-data catalogue before scheduling reports.'
                    : options.publishedDashboards.length === 0
                      ? 'Publish a governed dashboard before scheduling reports.'
                      : undefined
            }
        >
            <Form action={storeSchedule()} className="grid gap-5 pt-4">
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="workspace"
                            value="analytics-dashboard"
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                name="code"
                                label="Schedule code"
                                error={errors.code}
                            />
                            <Field
                                name="name"
                                label="Schedule name"
                                error={errors.name}
                            />
                            <SearchableSelect
                                id="schedule-dashboard"
                                name="filters[dashboard_id]"
                                label="Published dashboard"
                                options={options.publishedDashboards}
                            />
                            <SearchableSelect
                                id="schedule-county"
                                name="county_id"
                                label="County scope"
                                options={options.counties}
                                optional
                            />
                            <SearchableSelect
                                id="schedule-format"
                                name="format"
                                label="Artifact format"
                                options={['csv', 'xlsx', 'json', 'pdf'].map(
                                    option,
                                )}
                                defaultValue="pdf"
                            />
                            <SearchableSelect
                                id="schedule-frequency"
                                name="frequency"
                                label="Frequency"
                                options={['daily', 'weekly', 'monthly'].map(
                                    option,
                                )}
                                defaultValue="monthly"
                            />
                            <DatePickerField
                                name="filters[from]"
                                label="Reporting period from"
                            />
                            <DatePickerField
                                name="filters[to]"
                                label="Reporting period to"
                            />
                            <DatePickerField
                                name="next_run_at"
                                label="First execution"
                                includeTime
                                required
                            />
                            <SearchableMultiSelect
                                name="recipient_user_ids[]"
                                label="Authorized recipients"
                                options={options.users}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Send /> {analyticsCopy.save_activation}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ScheduleCard({
    schedule,
    capabilities,
}: {
    schedule: ReportSchedule;
    capabilities: Props['capabilities'];
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between">
                <div>
                    <CardTitle className="text-base">
                        {schedule.code}
                        {' · '}
                        {schedule.name}
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {analyticsCopy.next_run.replace(
                            ':date',
                            formatDate(schedule.nextRunAt),
                        )}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {schedule.referenceData
                            ? `Catalogue ${schedule.referenceData.version} · ${schedule.referenceData.checksum.slice(0, 12)}…`
                            : 'Legacy record · lineage not pinned'}
                    </p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Actions for ${schedule.code}`}
                        >
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <span>
                                <Eye /> {schedule.format.toUpperCase()}
                                {' · '}
                                {humanize(schedule.frequency)}
                            </span>
                        </DropdownMenuItem>
                        {capabilities.approveSchedule &&
                            schedule.status === 'draft' && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.patch(
                                            activate.url({
                                                schedule: schedule.id,
                                            }),
                                        )
                                    }
                                >
                                    <FileCheck2 />
                                    {analyticsCopy.activate_independently}
                                </DropdownMenuItem>
                            )}
                        {capabilities.manage &&
                            schedule.status === 'active' && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            run.url({ schedule: schedule.id }),
                                        )
                                    }
                                >
                                    <Play /> {analyticsCopy.queue_now}
                                </DropdownMenuItem>
                            )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </CardHeader>
            <CardContent className="grid gap-3">
                <div className="flex flex-wrap gap-2">
                    <Badge>{humanize(schedule.status)}</Badge>
                    <Badge variant="outline">
                        {schedule.format.toUpperCase()}
                    </Badge>
                    <Badge variant="outline">
                        {analyticsCopy.recipients.replace(
                            ':count',
                            String(schedule.recipientCount),
                        )}
                    </Badge>
                </div>
                {schedule.county ? (
                    <Link href={countyShow({ county: schedule.county.id })}>
                        <CountyIdentity county={schedule.county} compact />
                    </Link>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {analyticsCopy.national_recipients}
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    {analyticsCopy.created_by.replace(
                        ':name',
                        schedule.creator,
                    )}
                    {schedule.approver
                        ? ` · approved by ${schedule.approver}`
                        : ''}
                </p>
            </CardContent>
        </Card>
    );
}

function RunAction({ report }: { report: ReportRun }) {
    const analyticsCopy = usePage().props.localization.analytics;
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for report ${report.schedule.code}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> {analyticsCopy.view_execution}
                    </DropdownMenuItem>
                    {report.status === 'completed' && (
                        <DropdownMenuItem asChild>
                            <a href={download.url({ run: report.id })}>
                                <Download /> {analyticsCopy.download_artifact}
                            </a>
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {analyticsCopy.report_run.replace(
                                ':code',
                                report.schedule.code,
                            )}
                        </SheetTitle>
                        <SheetDescription>
                            {report.schedule.name}
                            {' · '}
                            {humanize(report.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail
                            label="Artifact checksum"
                            value={report.sha256 ?? 'Pending'}
                        />
                        <Detail
                            label="Record count"
                            value={
                                report.recordCount?.toLocaleString() ??
                                'Pending'
                            }
                        />
                        <Detail
                            label="Artifact size"
                            value={formatBytes(report.sizeBytes)}
                        />
                        <Detail
                            label="Started"
                            value={formatDate(report.startedAt)}
                        />
                        <Detail
                            label="Completed"
                            value={formatDate(report.completedAt)}
                        />
                        {report.errorDetail && (
                            <Detail
                                label="Failure detail"
                                value={report.errorDetail}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function MetricCard({
    title,
    value,
    detail,
}: {
    title: string;
    value: number;
    detail: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value.toLocaleString()}</p>
                <p className="mt-2 text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}
function Field({
    name,
    label,
    error,
    optional = false,
}: {
    name: string;
    label: string;
    error?: string;
    optional?: boolean;
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {optional && (
                    <span className="text-muted-foreground">
                        {' '}
                        {analyticsCopy.optional}
                    </span>
                )}
            </Label>
            <Input id={name} name={name} aria-invalid={Boolean(error)} />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
function TextField({
    name,
    label,
    error,
    optional = false,
}: {
    name: string;
    label: string;
    error?: string;
    optional?: boolean;
}) {
    const analyticsCopy = usePage().props.localization.analytics;

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {optional && (
                    <span className="text-muted-foreground">
                        {' '}
                        {analyticsCopy.optional}
                    </span>
                )}
            </Label>
            <Textarea
                id={name}
                name={name}
                rows={4}
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm break-all">{value}</p>
        </div>
    );
}
function humanize(value: string) {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}
function option(value: string): Option {
    return { id: value, name: humanize(value) };
}
function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString(DEFAULT_LOCALE) : '—';
}
function formatBytes(value: number | null) {
    if (value === null) {
        return '—';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}
