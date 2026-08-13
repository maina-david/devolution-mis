import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, FileBarChart, TrendingUp } from 'lucide-react';
import AssessmentAnalyticsFilters from '@/components/assessment-analytics-filters';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceDataTable from '@/components/workspace-data-table';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { index as assessmentIndex, show } from '@/routes/assessments';
import { exportMethod } from '@/routes/assessments/analytics';

type Filters = {
    from: string | null;
    to: string | null;
    cycle_id: string | null;
    county_id: string | null;
};
type Report = {
    summary: {
        publications: number;
        counties: number;
        cycles: number;
        averageScore: number | null;
    };
    cycles: Array<{
        id: string;
        code: string;
        name: string;
        periodStart: string;
        periodEnd: string;
        average: number;
        minimum: number;
        maximum: number;
        publications: number;
    }>;
    counties: Array<{
        county: CountyIdentityValue;
        results: Array<{
            assessmentId: string;
            cycleId: string;
            cycle: string;
            score: number;
            performanceBand: string;
            checksum: string;
        }>;
    }>;
    functions: {
        rows: Array<{
            cycleId: string;
            cycle: string;
            code: string;
            name: string;
            average: number;
        }>;
        pagination: WorkspacePagination;
    };
    rankings: {
        rows: Array<{
            publicationId: string;
            assessmentId: string;
            countyIdentity: CountyIdentityValue;
            score: string;
            performanceBand: string;
            rank: number;
            percentile: number;
        }>;
        pagination: WorkspacePagination;
    };
    options: {
        cycles: Array<{ id: string; name: string }>;
        counties: Array<{ id: string; name: string; logoUrl: string | null }>;
    };
};

export default function AssessmentAnalytics({
    report,
    filters,
}: {
    report: Report;
    filters: Filters;
}) {
    const page = usePage();
    const { localization } = page.props;
    const copy = localization.assessmentAnalytics;
    const query = {
        from: filters.from || undefined,
        to: filters.to || undefined,
        cycle_id: filters.cycle_id || undefined,
        county_id: filters.county_id || undefined,
    };
    const functionRows: WorkspaceRow[] = report.functions.rows.map((item) => ({
        id: `${item.cycleId}-${item.code}`,
        cells: [item.cycle, `${item.code} - ${item.name}`, `${item.average}%`],
    }));
    const rankingRows: WorkspaceRow[] = report.rankings.rows.map((item) => ({
        id: item.assessmentId,
        cells: [
            item.rank,
            item.countyIdentity,
            `${item.score}%`,
            copy[item.performanceBand] ?? item.performanceBand,
            `${item.percentile}%`,
        ],
        status: item.performanceBand,
    }));

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="self-start">
                    <Link href={assessmentIndex.url()}>
                        <ArrowLeft data-icon="inline-start" />
                        {copy.assessments}
                    </Link>
                </Button>
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.title}
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                {copy.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <AssessmentAnalyticsFilters
                                filters={filters}
                                cycles={report.options.cycles}
                                counties={report.options.counties}
                            />
                            <ExportMenu query={query} />
                        </div>
                    </div>
                </section>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label={copy.published_results}
                        value={report.summary.publications}
                    />
                    <Summary
                        label={copy.authorized_counties}
                        value={report.summary.counties}
                    />
                    <Summary
                        label={copy.assessment_cycles}
                        value={report.summary.cycles}
                    />
                    <Summary
                        label={copy.average_score}
                        value={
                            report.summary.averageScore === null
                                ? copy.not_available
                                : `${report.summary.averageScore}%`
                        }
                    />
                </div>
                {report.summary.publications === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileBarChart />
                            </EmptyMedia>
                            <EmptyTitle>{copy.empty_title}</EmptyTitle>
                            <EmptyDescription>
                                {copy.empty_description}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.cycle_trend}</CardTitle>
                                <CardDescription>
                                    {copy.cycle_trend_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {report.cycles.map((cycle) => (
                                    <div key={cycle.id} className="grid gap-2">
                                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                            <span className="font-medium">
                                                {cycle.name} ({cycle.code})
                                            </span>
                                            <span className="text-muted-foreground">
                                                {copy.average} {cycle.average}%{' '}
                                                {copy.from} {cycle.publications}{' '}
                                                {cycle.publications === 1
                                                    ? copy.publication
                                                    : copy.publications}
                                            </span>
                                        </div>
                                        <div
                                            className="h-3 overflow-hidden rounded-full bg-muted"
                                            aria-label={interpolate(
                                                copy.cycle_score_label,
                                                {
                                                    cycle: cycle.code,
                                                    score: String(
                                                        cycle.average,
                                                    ),
                                                },
                                            )}
                                            role="img"
                                        >
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${Math.min(100, Math.max(0, cycle.average))}%`,
                                                }}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {copy.range} {cycle.minimum}%{' '}
                                            {copy.to} {cycle.maximum}%
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <div className="grid gap-6 xl:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{copy.county_history}</CardTitle>
                                    <CardDescription>
                                        {copy.county_history_description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {report.counties.map((item) => (
                                        <div
                                            key={item.county.id}
                                            className="rounded-lg border p-4"
                                        >
                                            <CountyIdentity
                                                county={item.county}
                                                compact
                                            />
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {item.results.map((result) => (
                                                    <Button
                                                        key={
                                                            result.assessmentId
                                                        }
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={preserveDrilldownFilters(
                                                                show.url({
                                                                    assessment:
                                                                        result.assessmentId,
                                                                }),
                                                                page.url,
                                                            )}
                                                        >
                                                            {result.cycle}:{' '}
                                                            {result.score}%
                                                        </Link>
                                                    </Button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {copy.function_disaggregation}
                                    </CardTitle>
                                    <CardDescription>
                                        {copy.function_description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <WorkspaceDataTable
                                        columns={[
                                            copy.cycle,
                                            copy.function,
                                            copy.average,
                                        ]}
                                        rows={functionRows}
                                        pagination={report.functions.pagination}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.benchmark}</CardTitle>
                                <CardDescription>
                                    {copy.benchmark_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <WorkspaceDataTable
                                    columns={[
                                        copy.rank,
                                        copy.county,
                                        copy.score,
                                        copy.band,
                                        copy.percentile,
                                    ]}
                                    rows={rankingRows}
                                    pagination={report.rankings.pagination}
                                    getRowHref={(row) =>
                                        preserveDrilldownFilters(
                                            show.url({ assessment: row.id }),
                                            page.url,
                                        )
                                    }
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
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle>
                    {typeof value === 'number'
                        ? value.toLocaleString(locale)
                        : value}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}
function ExportMenu({ query }: { query: Record<string, string | undefined> }) {
    const copy = usePage().props.localization.assessmentAnalytics;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download data-icon="inline-start" />
                    {copy.export}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a href={exportMethod.url({ format }, { query })}>
                                <TrendingUp aria-hidden="true" />
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function interpolate(
    template: string,
    replacements: Record<string, string>,
): string {
    return Object.entries(replacements).reduce(
        (message, [key, value]) => message.replace(`:${key}`, value),
        template,
    );
}
