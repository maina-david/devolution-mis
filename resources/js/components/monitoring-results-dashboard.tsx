import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ChartNoAxesCombined,
    FolderKanban,
    ShieldCheck,
} from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { interpolate } from '@/hooks/use-localization';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { show as showCounty } from '@/routes/counties';
import { show as showProject } from '@/routes/projects';

export type MonitoringResults = {
    summary: {
        total: number;
        submitted: number;
        verified: number;
        rejected: number;
        projectSourced: number;
    };
    indicators: Array<{
        id: string;
        code: string;
        name: string;
        resultsLevel: string;
        unit: string;
        observations: number;
        average: number;
        minimum: number;
        maximum: number;
        latestPeriodEnd: string;
    }>;
    disaggregations: Array<{
        indicatorId: string;
        code: string;
        name: string;
        dimension: string;
        observations: number;
        average: number;
        latestPeriodEnd: string;
    }>;
    performance: {
        summary: {
            series: number;
            withTarget: number;
            met: number;
            offTrack: number;
            averageAttainment: number | null;
        };
        methodology: string;
        rows: Array<{
            id: string;
            indicator: {
                id: string;
                code: string;
                name: string;
                unit: string;
                direction: string;
            };
            county: CountyIdentityValue;
            programme: string | null;
            dimension: string;
            periodEnd: string;
            actual: number;
            target: number | null;
            variance: number | null;
            variancePercentage: number | null;
            attainment: number | null;
            status: 'met' | 'off_track' | 'target_missing';
        }>;
        trends: Array<{
            key: string;
            indicator: {
                id: string;
                code: string;
                name: string;
                unit: string;
            };
            county: CountyIdentityValue;
            dimension: string;
            points: Array<{
                period: string;
                actual: number;
                target: number | null;
            }>;
        }>;
    };
    projectContributions: Array<{
        id: string;
        indicator: { code: string; name: string; unit: string };
        county: CountyIdentityValue;
        project: { id: string; code: string; title: string };
        periodEnd: string;
        dimension: string;
        value: string | number;
        verificationStatus: string;
        qualityStatus: string;
    }>;
};

export default function MonitoringResultsDashboard({
    results,
}: {
    results: MonitoringResults;
}) {
    const page = usePage();
    const copy = page.props.localization.monitoringResults;
    const locale = page.props.localization.current;
    const drilldown = (url: string) => preserveDrilldownFilters(url, page.url);
    const summaryCards = [
        {
            label: copy.scoped_observations,
            value: results.summary.total,
            icon: Activity,
        },
        {
            label: copy.verified_results,
            value: results.summary.verified,
            icon: ShieldCheck,
        },
        {
            label: copy.awaiting_review,
            value: results.summary.submitted,
            icon: ChartNoAxesCombined,
        },
        {
            label: copy.project_sourced,
            value: results.summary.projectSourced,
            icon: FolderKanban,
        },
    ];

    return (
        <section
            aria-labelledby="results-dashboard-title"
            className="flex flex-col gap-5"
        >
            <div>
                <h2 id="results-dashboard-title" className="text-xl font-bold">
                    {copy.results_dashboard}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {copy.results_dashboard_description}
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {summaryCards.map(({ label, value, icon: Icon }) => (
                    <Card key={label}>
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardDescription>{label}</CardDescription>
                            <Icon aria-hidden="true" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold tabular-nums">
                                {value.toLocaleString(locale)}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-5 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{copy.verified_indicator_ranges}</CardTitle>
                        <CardDescription>
                            {copy.verified_indicator_ranges_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        {results.indicators.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{copy.indicator}</TableHead>
                                        <TableHead>{copy.level}</TableHead>
                                        <TableHead className="text-right">
                                            {copy.average}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {copy.range}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {copy.records}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {results.indicators.map((indicator) => (
                                        <TableRow key={indicator.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {indicator.code}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {indicator.name}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {indicator.resultsLevel}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {indicator.average.toLocaleString(
                                                    locale,
                                                )}{' '}
                                                {indicator.unit}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {indicator.minimum.toLocaleString(
                                                    locale,
                                                )}
                                                {copy.range_separator}
                                                {indicator.maximum.toLocaleString(
                                                    locale,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {indicator.observations.toLocaleString(
                                                    locale,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <ResultsEmpty
                                title={copy.no_verified_numeric_results}
                                description={
                                    copy.no_verified_numeric_results_description
                                }
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.disaggregation}</CardTitle>
                        <CardDescription>
                            {copy.disaggregation_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        {results.disaggregations.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{copy.indicator}</TableHead>
                                        <TableHead>{copy.dimension}</TableHead>
                                        <TableHead className="text-right">
                                            {copy.average}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {copy.records}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {results.disaggregations.map((item) => (
                                        <TableRow
                                            key={`${item.indicatorId}-${item.dimension}`}
                                        >
                                            <TableCell>
                                                <span className="font-medium">
                                                    {item.code}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.name}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {item.dimension}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.average.toLocaleString(
                                                    locale,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.observations.toLocaleString(
                                                    locale,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <ResultsEmpty
                                title={copy.no_disaggregated_results}
                                description={
                                    copy.no_disaggregated_results_description
                                }
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            <TargetPerformance performance={results.performance} />

            <Card>
                <CardHeader>
                    <CardTitle>{copy.project_result_contributions}</CardTitle>
                    <CardDescription>
                        {copy.project_result_contributions_description}
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto p-0">
                    {results.projectContributions.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{copy.project}</TableHead>
                                    <TableHead>{copy.county}</TableHead>
                                    <TableHead>{copy.indicator}</TableHead>
                                    <TableHead>{copy.dimension}</TableHead>
                                    <TableHead className="text-right">
                                        {copy.value}
                                    </TableHead>
                                    <TableHead>{copy.quality_state}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results.projectContributions.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <Link
                                                className="font-medium hover:underline"
                                                href={drilldown(
                                                    showProject.url({
                                                        project:
                                                            item.project.id,
                                                    }),
                                                )}
                                            >
                                                {item.project.code}
                                            </Link>
                                            <span className="block text-xs text-muted-foreground">
                                                {item.project.title}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={drilldown(
                                                    showCounty.url({
                                                        county: item.county.id,
                                                    }),
                                                )}
                                            >
                                                <CountyIdentity
                                                    county={item.county}
                                                    compact
                                                />
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {item.indicator.code}{' '}
                                            {copy.separator}{' '}
                                            {item.indicator.name}
                                        </TableCell>
                                        <TableCell>{item.dimension}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.value} {item.indicator.unit}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {item.verificationStatus}{' '}
                                                {copy.separator}{' '}
                                                {item.qualityStatus}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <ResultsEmpty
                            title={copy.no_project_contributions}
                            description={
                                copy.no_project_contributions_description
                            }
                        />
                    )}
                </CardContent>
            </Card>
        </section>
    );
}

function TargetPerformance({
    performance,
}: {
    performance: MonitoringResults['performance'];
}) {
    const page = usePage();
    const copy = page.props.localization.monitoringResults;
    const locale = page.props.localization.current;
    const drilldown = (url: string) => preserveDrilldownFilters(url, page.url);
    const performanceChartConfig = {
        actual: { label: copy.verified_actual, color: 'var(--chart-1)' },
        target: { label: copy.verified_target, color: 'var(--chart-2)' },
    } satisfies ChartConfig;

    return (
        <section
            aria-labelledby="target-performance-title"
            className="flex flex-col gap-5"
        >
            <div>
                <h3 id="target-performance-title" className="text-lg font-bold">
                    {copy.target_performance_trends}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {copy.target_performance_trends_description}
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <PerformanceMetric
                    label={copy.series_with_targets}
                    value={`${performance.summary.withTarget}/${performance.summary.series}`}
                />
                <PerformanceMetric
                    label={copy.targets_met}
                    value={performance.summary.met.toLocaleString(locale)}
                />
                <PerformanceMetric
                    label={copy.off_track}
                    value={performance.summary.offTrack.toLocaleString(locale)}
                />
                <PerformanceMetric
                    label={copy.average_attainment}
                    value={
                        performance.summary.averageAttainment === null
                            ? '—'
                            : `${performance.summary.averageAttainment}%`
                    }
                />
            </div>
            <Alert>
                <ChartNoAxesCombined aria-hidden="true" />
                <AlertTitle>{copy.calculation_method}</AlertTitle>
                <AlertDescription>{performance.methodology}</AlertDescription>
            </Alert>
            {performance.trends.length > 0 && (
                <div className="grid gap-5 xl:grid-cols-2">
                    {performance.trends.slice(0, 4).map((trend) => (
                        <Card key={trend.key}>
                            <CardHeader>
                                <CardTitle>
                                    {trend.indicator.code} {copy.separator}{' '}
                                    {trend.indicator.name}
                                </CardTitle>
                                <CardDescription className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={drilldown(
                                            showCounty.url({
                                                county: trend.county.id,
                                            }),
                                        )}
                                        className="hover:underline"
                                    >
                                        <CountyIdentity
                                            county={trend.county}
                                            compact
                                        />
                                    </Link>
                                    <span>
                                        {trend.dimension} {copy.separator}{' '}
                                        {trend.indicator.unit}
                                    </span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ChartContainer
                                    config={performanceChartConfig}
                                    className="h-64 w-full"
                                    role="img"
                                    aria-label={interpolate(
                                        copy.trend_accessible_name,
                                        {
                                            indicator: trend.indicator.code,
                                            county: trend.county.name,
                                        },
                                    )}
                                >
                                    <LineChart
                                        accessibilityLayer
                                        data={trend.points}
                                        margin={{ left: 4, right: 12 }}
                                    >
                                        <CartesianGrid vertical={false} />
                                        <XAxis
                                            dataKey="period"
                                            tickLine={false}
                                            axisLine={false}
                                            tickMargin={8}
                                            tickFormatter={(value: string) =>
                                                value.slice(0, 7)
                                            }
                                        />
                                        <YAxis
                                            tickLine={false}
                                            axisLine={false}
                                            width={44}
                                        />
                                        <ChartTooltip
                                            content={<ChartTooltipContent />}
                                        />
                                        <ChartLegend
                                            content={<ChartLegendContent />}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="actual"
                                            stroke="var(--color-actual)"
                                            strokeWidth={2}
                                            dot={{ r: 3 }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="target"
                                            stroke="var(--color-target)"
                                            strokeWidth={2}
                                            strokeDasharray="5 4"
                                            dot={{ r: 3 }}
                                            connectNulls={false}
                                        />
                                    </LineChart>
                                </ChartContainer>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>{copy.latest_target_variance}</CardTitle>
                    <CardDescription>
                        {copy.latest_target_variance_description}
                    </CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto p-0">
                    {performance.rows.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{copy.indicator}</TableHead>
                                    <TableHead>{copy.county}</TableHead>
                                    <TableHead>{copy.period}</TableHead>
                                    <TableHead className="text-right">
                                        {copy.actual}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {copy.target}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {copy.variance}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {copy.attainment}
                                    </TableHead>
                                    <TableHead>{copy.status}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {performance.rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <span className="font-medium">
                                                {row.indicator.code}
                                            </span>
                                            <span className="block max-w-64 text-xs text-muted-foreground">
                                                {row.indicator.name}{' '}
                                                {copy.separator} {row.dimension}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={drilldown(
                                                    showCounty.url({
                                                        county: row.county.id,
                                                    }),
                                                )}
                                            >
                                                <CountyIdentity
                                                    county={row.county}
                                                    compact
                                                />
                                            </Link>
                                        </TableCell>
                                        <TableCell>{row.periodEnd}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.actual.toLocaleString(locale)}{' '}
                                            {row.indicator.unit}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.target === null
                                                ? '—'
                                                : row.target.toLocaleString(
                                                      locale,
                                                  )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.variance === null
                                                ? '—'
                                                : `${row.variance > 0 ? '+' : ''}${row.variance.toLocaleString(locale)} (${row.variancePercentage ?? '—'}%)`}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.attainment === null
                                                ? '—'
                                                : `${row.attainment}%`}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    row.status === 'met'
                                                        ? 'default'
                                                        : row.status ===
                                                            'off_track'
                                                          ? 'destructive'
                                                          : 'outline'
                                                }
                                            >
                                                {copy[row.status] ?? row.status}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <ResultsEmpty
                            title={copy.no_verified_actuals}
                            description={copy.no_verified_actuals_description}
                        />
                    )}
                </CardContent>
            </Card>
        </section>
    );
}

function PerformanceMetric({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-bold tabular-nums">{value}</p>
            </CardContent>
        </Card>
    );
}

function ResultsEmpty({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <Empty className="min-h-52 border-0">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <ChartNoAxesCombined aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}
