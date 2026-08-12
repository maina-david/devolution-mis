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
            item.performanceBand,
            `${item.percentile}%`,
        ],
        status: item.performanceBand,
    }));

    return (
        <>
            <Head title="Assessment comparative analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="self-start">
                    <Link href={assessmentIndex.url()}>
                        <ArrowLeft data-icon="inline-start" />
                        Assessments
                    </Link>
                </Button>
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                Assessment comparative analytics
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                Compare immutable published results across
                                authorized counties, cycles and devolved
                                functions.
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
                        label="Published results"
                        value={report.summary.publications}
                    />
                    <Summary
                        label="Authorized counties"
                        value={report.summary.counties}
                    />
                    <Summary
                        label="Assessment cycles"
                        value={report.summary.cycles}
                    />
                    <Summary
                        label="Average score"
                        value={
                            report.summary.averageScore === null
                                ? 'Not available'
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
                            <EmptyTitle>No published results match</EmptyTitle>
                            <EmptyDescription>
                                Adjust the date, cycle or county filters. Draft
                                and mutable assessment records are intentionally
                                excluded.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Cycle trend</CardTitle>
                                <CardDescription>
                                    Average, minimum and maximum scores from
                                    immutable county publications.
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
                                                Average {cycle.average}% from{' '}
                                                {cycle.publications} publication
                                                {cycle.publications === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                        </div>
                                        <div
                                            className="h-3 overflow-hidden rounded-full bg-muted"
                                            aria-label={`${cycle.code} average score ${cycle.average} percent`}
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
                                            Range {cycle.minimum}% to{' '}
                                            {cycle.maximum}%
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <div className="grid gap-6 xl:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>County history</CardTitle>
                                    <CardDescription>
                                        Published score progression within the
                                        viewer's authorized portfolio.
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
                                        Function disaggregation
                                    </CardTitle>
                                    <CardDescription>
                                        Average performance by devolved function
                                        and assessment cycle.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <WorkspaceDataTable
                                        columns={[
                                            'Cycle',
                                            'Function',
                                            'Average',
                                        ]}
                                        rows={functionRows}
                                        pagination={report.functions.pagination}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                        <Card>
                            <CardHeader>
                                <CardTitle>Selected-cycle benchmark</CardTitle>
                                <CardDescription>
                                    Ranks and percentiles are recalculated only
                                    within the viewer's authorized county scope.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <WorkspaceDataTable
                                    columns={[
                                        'Rank',
                                        'County',
                                        'Score',
                                        'Band',
                                        'Percentile',
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
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle>
                    {typeof value === 'number' ? value.toLocaleString() : value}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}
function ExportMenu({ query }: { query: Record<string, string | undefined> }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download data-icon="inline-start" />
                    Export
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
