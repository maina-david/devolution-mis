import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Fingerprint,
    GraduationCap,
    TrendingUp,
} from 'lucide-react';
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
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';
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
type QuestionMetric = {
    id: string;
    question: string;
    variantGroup: string;
    difficulty: string;
    tags: string[];
    bankVersion: number | null;
    bankChecksum: string | null;
    lineageCount: number;
    suppressed: boolean;
    responseCount: number | null;
    correctRate: number | null;
    discrimination: number | null;
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
    questionBank: {
        hasData: boolean;
        attempts: number | null;
        suppressed: boolean;
        lineages: number;
        rows: QuestionMetric[];
        pagination: WorkspacePagination;
    };
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
    const { localization } = page.props;
    const copy = localization.learningAnalytics;
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
                localization.current,
                copy,
            ),
            metric(
                item.completed,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
            ),
            metric(
                item.completionRate,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
            ),
            metric(
                item.averageProgress,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
            ),
            metric(
                item.averageScore,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
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
                localization.current,
                copy,
            ),
            metric(
                item.completed,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
            ),
            metric(
                item.completionRate,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
            ),
            metric(
                item.averageProgress,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
            ),
            metric(
                item.averageScore,
                item.suppressed,
                report.privacy.minimumCellSize,
                localization.current,
                copy,
                true,
            ),
        ],
        href: preserveDrilldownFilters(
            showCounty.url({ county: item.county.id }),
            page.url,
        ),
    }));
    const questionRows: WorkspaceRow[] = report.questionBank.rows.map(
        (item) => ({
            id: item.id,
            cells: [
                item.question,
                item.variantGroup,
                item.difficulty,
                item.tags.join(', ') || '—',
                metric(
                    item.responseCount,
                    item.suppressed,
                    report.privacy.minimumCellSize,
                    localization.current,
                    copy,
                ),
                metric(
                    item.correctRate,
                    item.suppressed,
                    report.privacy.minimumCellSize,
                    localization.current,
                    copy,
                    true,
                ),
                item.suppressed
                    ? copy.suppressed.replace(
                          ':count',
                          report.privacy.minimumCellSize.toLocaleString(
                              localization.current,
                          ),
                      )
                    : item.discrimination === null
                      ? '—'
                      : copy.percentage_points.replace(
                            ':value',
                            item.discrimination.toLocaleString(
                                localization.current,
                                { maximumFractionDigits: 2 },
                            ),
                        ),
                item.lineageCount > 1
                    ? copy.multiple_retained_lineages
                    : item.bankVersion === null
                      ? copy.legacy_unversioned
                      : copy.bank_version.replace(
                            ':version',
                            item.bankVersion.toLocaleString(
                                localization.current,
                            ),
                        ),
                item.lineageCount,
            ],
        }),
    );

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button variant="ghost" asChild className="self-start">
                    <Link href={learningIndex.url()}>
                        <ArrowLeft aria-hidden="true" /> {copy.e_learning}
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
                        <ExportMenu query={query} />
                    </div>
                </section>
                <DateRangeFilter
                    cycles={[]}
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search ?? ''}
                    searchPlaceholder={copy.search_courses}
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
                            key: 'course_id',
                            label: copy.course,
                            value: filters.course_id,
                            options: report.options.courses,
                        },
                        {
                            key: 'status',
                            label: copy.enrollment_status,
                            value: filters.status,
                            options: [
                                { id: 'enrolled', name: copy.enrolled },
                                { id: 'in_progress', name: copy.in_progress },
                                { id: 'completed', name: copy.completed },
                                { id: 'withdrawn', name: copy.withdrawn },
                            ],
                        },
                    ]}
                />
                <Alert>
                    <AlertTitle>{copy.privacy_title}</AlertTitle>
                    <AlertDescription>
                        {copy.privacy_description.replace(
                            ':count',
                            report.privacy.minimumCellSize.toLocaleString(
                                localization.current,
                            ),
                        )}
                    </AlertDescription>
                </Alert>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label={copy.enrollments}
                        value={metric(
                            report.summary.enrollments,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                            localization.current,
                            copy,
                        )}
                    />
                    <Summary
                        label={copy.active_learners}
                        value={metric(
                            report.summary.active,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                            localization.current,
                            copy,
                        )}
                    />
                    <Summary
                        label={copy.completion_rate}
                        value={metric(
                            report.summary.completionRate,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                            localization.current,
                            copy,
                            true,
                        )}
                    />
                    <Summary
                        label={copy.certificates}
                        value={metric(
                            report.summary.certificates,
                            report.summary.suppressed,
                            report.privacy.minimumCellSize,
                            localization.current,
                            copy,
                        )}
                    />
                </div>
                {!report.summary.hasData ? (
                    <WorkspaceEmptyState
                        title={copy.empty_title}
                        description={copy.empty_description}
                        className="min-h-72"
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.trend_title}</CardTitle>
                                <CardDescription>
                                    {copy.trend_description}
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
                                                {formatPeriod(
                                                    item.period,
                                                    localization.current,
                                                )}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {item.suppressed
                                                    ? copy.suppressed.replace(
                                                          ':count',
                                                          report.privacy.minimumCellSize.toLocaleString(
                                                              localization.current,
                                                          ),
                                                      )
                                                    : copy.completed_of
                                                          .replace(
                                                              ':completed',
                                                              Number(
                                                                  item.completed,
                                                              ).toLocaleString(
                                                                  localization.current,
                                                              ),
                                                          )
                                                          .replace(
                                                              ':enrollments',
                                                              Number(
                                                                  item.enrollments,
                                                              ).toLocaleString(
                                                                  localization.current,
                                                              ),
                                                          )}
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
                                <CardTitle>
                                    {copy.question_bank_title}
                                </CardTitle>
                                <CardDescription>
                                    {copy.question_bank_description}
                                </CardDescription>
                                <Marker variant="border">
                                    <MarkerIcon>
                                        <Fingerprint />
                                    </MarkerIcon>
                                    <MarkerContent>
                                        {copy.question_bank_lineage
                                            .replace(
                                                ':attempts',
                                                report.questionBank.suppressed
                                                    ? copy.suppressed.replace(
                                                          ':count',
                                                          report.privacy.minimumCellSize.toLocaleString(
                                                              localization.current,
                                                          ),
                                                      )
                                                    : Number(
                                                          report.questionBank
                                                              .attempts,
                                                      ).toLocaleString(
                                                          localization.current,
                                                      ),
                                            )
                                            .replace(
                                                ':lineages',
                                                report.questionBank.lineages.toLocaleString(
                                                    localization.current,
                                                ),
                                            )}
                                    </MarkerContent>
                                </Marker>
                            </CardHeader>
                            <CardContent>
                                {report.questionBank.hasData ? (
                                    <WorkspaceDataTable
                                        columns={[
                                            copy.question,
                                            copy.variant_group,
                                            copy.difficulty,
                                            copy.tags,
                                            copy.responses,
                                            copy.correct_rate,
                                            copy.discrimination,
                                            copy.bank_lineage,
                                            copy.lineage_count,
                                        ]}
                                        rows={questionRows}
                                        pagination={
                                            report.questionBank.pagination
                                        }
                                    />
                                ) : (
                                    <WorkspaceEmptyState
                                        title={copy.question_bank_empty_title}
                                        description={
                                            copy.question_bank_empty_description
                                        }
                                    />
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.course_performance}</CardTitle>
                                <CardDescription>
                                    {copy.course_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        copy.code,
                                        copy.course,
                                        copy.category,
                                        copy.enrollments,
                                        copy.completed,
                                        copy.completion,
                                        copy.average_progress,
                                        copy.average_score,
                                    ]}
                                    rows={courseRows}
                                    pagination={report.courses.pagination}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.county_outcomes}</CardTitle>
                                <CardDescription>
                                    {copy.county_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkspaceDataTable
                                    columns={[
                                        copy.county,
                                        copy.enrollments,
                                        copy.completed,
                                        copy.completion,
                                        copy.average_progress,
                                        copy.average_score,
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
    locale: string,
    copy: Record<string, string>,
    percentage = false,
): string | number {
    if (suppressed) {
        return copy.suppressed.replace(
            ':count',
            minimumCellSize.toLocaleString(locale),
        );
    }

    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat(locale, {
        maximumFractionDigits: 2,
        ...(percentage ? { style: 'percent' as const } : {}),
    }).format(percentage ? value / 100 : value);
}

function formatPeriod(period: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${period}-01T00:00:00Z`));
}

function Summary({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardDescription>{label}</CardDescription>
                <TrendingUp
                    className="size-4 text-primary"
                    aria-hidden="true"
                />
            </CardHeader>
            <CardContent className="text-3xl font-bold">{value}</CardContent>
        </Card>
    );
}

function ExportMenu({ query }: { query: Record<string, string | undefined> }) {
    const copy = usePage().props.localization.learningAnalytics;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download aria-hidden="true" /> {copy.export_evidence}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <DropdownMenuItem key={format} asChild>
                        <a href={exportMethod.url({ format }, { query })}>
                            <GraduationCap aria-hidden="true" />{' '}
                            {format.toUpperCase()}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
