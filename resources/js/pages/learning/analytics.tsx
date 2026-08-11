import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, GraduationCap, TrendingUp } from 'lucide-react';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { show as showCounty } from '@/routes/counties';
import { index as learningIndex } from '@/routes/learning';
import { exportMethod } from '@/routes/learning/analytics';

type Filters = {
    from?: string | null;
    to?: string | null;
    county_id?: string | null;
    course_id?: string | null;
    status?: string | null;
    search?: string | null;
};
type CourseMetric = {
    id: string;
    code: string;
    title: string;
    category: string;
    suppressed: boolean;
    enrollments: number | null;
    completed: number | null;
    completionRate: number | null;
    averageProgress: number | null;
    averageScore: number | null;
};
type CountyMetric = {
    county: CountyIdentityValue;
    suppressed: boolean;
    enrollments: number | null;
    completed: number | null;
    completionRate: number | null;
    averageProgress: number | null;
    averageScore: number | null;
};
type Report = {
    privacy: { minimumCellSize: number };
    summary: {
        hasData: boolean;
        suppressed: boolean;
        enrollments: number | null;
        active: number | null;
        completed: number | null;
        completionRate: number | null;
        certificates: number | null;
        averageScore: number | null;
        averageProgress: number | null;
    };
    courses: { rows: CourseMetric[]; pagination: WorkspacePagination };
    counties: { rows: CountyMetric[]; pagination: WorkspacePagination };
    trend: Array<{
        period: string;
        suppressed: boolean;
        enrollments: number | null;
        completed: number | null;
    }>;
    options: {
        counties: CountyIdentityValue[];
        courses: Array<{ id: string; name: string }>;
    };
};

export default function LearningAnalytics({
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
        course_id: filters.course_id || undefined,
        status: filters.status || undefined,
        search: filters.search || undefined,
    };
    const courseRows: WorkspaceRow[] = report.courses.rows.map((item) => ({
        id: item.id,
        cells: [
            item.code,
            item.title,
            item.category,
            metric(
                item.enrollments,
                item.suppressed,
                report.privacy.minimumCellSize,
            ),
            metric(
                item.completed,
                item.suppressed,
                report.privacy.minimumCellSize,
            ),
            metric(
                item.completionRate,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
            metric(
                item.averageProgress,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
            metric(
                item.averageScore,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
        ],
    }));
    const countyRows: WorkspaceRow[] = report.counties.rows.map((item) => ({
        id: item.county.id,
        meta: { countyId: item.county.id },
        cells: [
            item.county,
            metric(
                item.enrollments,
                item.suppressed,
                report.privacy.minimumCellSize,
            ),
            metric(
                item.completed,
                item.suppressed,
                report.privacy.minimumCellSize,
            ),
            metric(
                item.completionRate,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
            metric(
                item.averageProgress,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
            metric(
                item.averageScore,
                item.suppressed,
                report.privacy.minimumCellSize,
                '%',
            ),
        ],
        href: preserveDrilldownFilters(
            showCounty.url({ current_team: teamSlug, county: item.county.id }),
            page.url,
        ),
    }));

    return (
        <>
            <Head title="Learning analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="self-start">
                    <Link href={learningIndex.url(teamSlug)}>
                        <ArrowLeft /> E-Learning
                    </Link>
                </Button>
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                Learning analytics
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                Completion, progress, assessment and certificate
                                outcomes across your authorized county
                                portfolio.
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
                    searchPlaceholder="Search courses"
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
                            key: 'course_id',
                            label: 'Course',
                            value: filters.course_id,
                            options: report.options.courses,
                        },
                        {
                            key: 'status',
                            label: 'Enrollment status',
                            value: filters.status,
                            options: [
                                { id: 'enrolled', name: 'Enrolled' },
                                { id: 'in_progress', name: 'In progress' },
                                { id: 'completed', name: 'Completed' },
                                { id: 'withdrawn', name: 'Withdrawn' },
                            ],
                        },
                    ]}
                />
                <Alert>
                    <AlertTitle>Privacy-protected aggregates</AlertTitle>
                    <AlertDescription>
                        Results based on fewer than{' '}
                        {report.privacy.minimumCellSize} enrollments are
                        suppressed on screen and in every export.
                    </AlertDescription>
                </Alert>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Enrollments"
                        value={metric(
                            report.summary.enrollments,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                        )}
                    />
                    <Summary
                        label="Active learners"
                        value={metric(
                            report.summary.active,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                        )}
                    />
                    <Summary
                        label="Completion rate"
                        value={metric(
                            report.summary.completionRate,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                            '%',
                        )}
                    />
                    <Summary
                        label="Certificates"
                        value={metric(
                            report.summary.certificates,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                        )}
                    />
                </div>
                {!report.summary.hasData ? (
                    <WorkspaceEmptyState
                        title="No learning activity matches"
                        description="Adjust the date, county, course or enrollment-status filters."
                        className="min-h-72"
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Enrollment and completion trend
                                </CardTitle>
                                <CardDescription>
                                    Monthly activity within the selected period
                                    and authorized scope.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {report.trend.map((item) => (
                                    <div
                                        key={item.period}
                                        className="grid gap-2"
                                    >
                                        <div className="flex justify-between gap-4 text-sm">
                                            <span className="font-medium">
                                                {item.period}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {item.suppressed
                                                    ? `Suppressed (<${report.privacy.minimumCellSize})`
                                                    : `${item.completed} completed of ${item.enrollments}`}
                                            </span>
                                        </div>
                                        <div className="h-3 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${item.enrollments && item.completed !== null ? (item.completed / item.enrollments) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Course performance</CardTitle>
                                <CardDescription>
                                    Aggregate outcomes without exposing
                                    learner-level records.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        'Code',
                                        'Course',
                                        'Category',
                                        'Enrollments',
                                        'Completed',
                                        'Completion',
                                        'Average progress',
                                        'Average score',
                                    ]}
                                    rows={courseRows}
                                    pagination={report.courses.pagination}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>County outcomes</CardTitle>
                                <CardDescription>
                                    County identity and drill-through preserve
                                    the active analytics filters.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        'County',
                                        'Enrollments',
                                        'Completed',
                                        'Completion',
                                        'Average progress',
                                        'Average score',
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

function metric(
    value: number | null,
    suppressed: boolean,
    minimumCellSize: number,
    suffix = '',
): string | number {
    if (suppressed) {
        return `Suppressed (<${minimumCellSize})`;
    }

    return value === null ? '—' : `${value}${suffix}`;
}

function Summary({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardDescription>{label}</CardDescription>
                <TrendingUp className="size-4 text-primary" />
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
                    <Download /> Export evidence
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <DropdownMenuItem key={format} asChild>
                        <a
                            href={exportMethod.url(
                                {
                                    current_team: teamSlug,
                                    format,
                                },
                                { query },
                            )}
                        >
                            <GraduationCap /> {format.toUpperCase()}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
