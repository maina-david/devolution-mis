import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    CalendarDays,
    DownloadIcon,
    Eye,
    FileUp,
    GraduationCap,
    MoreHorizontal,
    Plus,
    ShieldCheck,
    Video,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { interpolate } from '@/hooks/use-localization';
import {
    download as downloadEvidence,
    preview as previewEvidence,
} from '@/routes/evidence';
import { index as knowledgeIndex } from '@/routes/knowledge';
import { store as storeAssessment } from '@/routes/learning/assessments';
import { show as showCertificate } from '@/routes/learning/certificates';
import {
    show as showClassroom,
    store as storeClassroom,
} from '@/routes/learning/classrooms';
import {
    store as storeCohort,
    transition as transitionCohort,
} from '@/routes/learning/cohorts';
import { store as addCohortMember } from '@/routes/learning/cohorts/members';
import { store as storeCourse, transition } from '@/routes/learning/courses';
import { store as generateOfflinePackage } from '@/routes/learning/courses/offline-packages';
import { store as enroll } from '@/routes/learning/enrollments';
import { store as submitOfflineSync } from '@/routes/learning/enrollments/offline-syncs';
import { complete } from '@/routes/learning/lessons';
import { store as storeLessonAsset } from '@/routes/learning/lessons/assets';
import { download as downloadOfflinePackage } from '@/routes/learning/offline-packages';
import { store as decideOfflineSync } from '@/routes/learning/offline-syncs/decision';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Question = {
    id: string;
    question: string;
    options: Record<string, string>;
    points: string;
};
type Lesson = {
    id: string;
    title: string;
    summary: string | null;
    contentType: string;
    contentBody: string | null;
    contentUrl: string | null;
    downloadable: boolean;
    estimatedMinutes: number;
    assetMetadata: {
        rights_holder?: string;
        licence?: string;
        accessible_alternative?: string;
        transcript_available?: boolean;
    } | null;
    assets: Array<{
        id: string;
        title: string;
        originalName: string | null;
        mimeType: string | null;
        sourceType: string;
        scanStatus: string;
        ocrStatus: string;
        checksum: string;
    }>;
    questions: Question[];
};
type Course = {
    id: string;
    code: string;
    title: string;
    summary: string;
    description: string;
    category: string;
    level: string;
    deliveryMode: string;
    language: string;
    estimatedMinutes: number;
    passingScore: string;
    maximumAttempts: number;
    status: string;
    sector: string | null;
    county: CountyIdentityValue | null;
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    owner: string;
    moduleCount: number;
    enrollmentCount: number;
    knowledgeRecommendations: Array<{
        id: string;
        reference: string;
        title: string;
        summary: string;
        type: string;
    }>;
    offlinePackageAttempt: null | {
        version: number;
        status: string;
        failedAt: string | null;
        failureMessage: string | null;
    };
    offlinePackage: null | {
        id: string;
        version: number;
        status: string;
        sizeBytes: number | null;
        checksum: string | null;
        manifestChecksum: string | null;
        generatedAt: string | null;
        canDownload: boolean;
    };
    modules: Array<{
        id: string;
        title: string;
        description: string | null;
        lessons: Lesson[];
    }>;
    classrooms: Array<{
        id: string;
        title: string;
        facilitator: string;
        startsAt: string;
        endsAt: string;
        platform: string;
        joinUrl: string;
        capacity: number | null;
        status: string;
        canRecordAttendance: boolean;
        attendance: null | {
            status: string;
            minutes: number;
            recordedAt: string;
        };
    }>;
    enrollment: null | {
        id: string;
        status: string;
        progress: string;
        bestScore: string | null;
        attempts: number;
        completedLessonIds: string[];
        certificate: null | {
            id: string;
            number: string;
            verificationCode: string;
        };
    };
};
type Props = {
    courses: {
        data: Course[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    offlineSyncs: {
        data: OfflineSync[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    cohorts: {
        data: Cohort[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: Record<string, string | undefined>;
    capabilities: { manage: boolean; review: boolean; enroll: boolean };
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: {
        sectors: Option[];
        facilitators: Option[];
        counties: Option[];
        cohortCourses: Option[];
        instructors: Option[];
        cohortEnrollments: Option[];
    };
};
type Cohort = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    course: { id: string; code: string; title: string };
    instructor: string;
    county: CountyIdentityValue | null;
    capacity: number;
    membersCount: number;
    members: Array<{ id: string; name: string }>;
    enrollmentOpensOn: string;
    enrollmentClosesOn: string;
    startsAt: string;
    endsAt: string;
    status: string;
};
type OfflineSync = {
    id: string;
    clientSyncId: string;
    course: { id: string; code: string; title: string };
    packageVersion: number;
    learner: string;
    county: CountyIdentityValue | null;
    status: string;
    eventCount: number;
    payloadChecksum: string;
    decisionChecksum: string | null;
    decisionReason: string | null;
    submittedAt: string;
    reviewedAt: string | null;
    reviewer: string | null;
};
const statusIds = [
    'draft',
    'quality_review',
    'published',
    'retired',
    'open',
    'active',
    'completed',
    'cancelled',
];

export default function Learning({
    courses,
    cohorts,
    offlineSyncs,
    filters,
    capabilities,
    catalogue,
    options,
}: Props) {
    const { localization } = usePage().props;
    const copy = localization.learning;
    const locale = localization.current;
    const statuses = statusIds.map((id) => ({
        id,
        name: displayValue(copy, id),
    }));
    const rows: WorkspaceRow[] = courses.data.map((course) => ({
        id: course.id,
        status: course.status,
        cells: [
            course.code,
            course.title,
            course.category,
            course.level,
            course.county ?? copy.national,
            course.referenceData
                ? `v${course.referenceData.version}`
                : copy.legacy_unpinned,
            course.referenceData?.checksum ?? copy.legacy_unpinned,
            interpolate(copy.module_duration, {
                modules: course.moduleCount,
                minutes: course.estimatedMinutes,
            }),
            course.enrollment
                ? `${course.enrollment.progress}%`
                : interpolate(copy.enrolled_count, {
                      count: course.enrollmentCount,
                  }),
            displayValue(copy, course.status),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: courses.current_page,
        lastPage: courses.last_page,
        perPage: courses.per_page,
        total: courses.total,
    };
    const syncRows: WorkspaceRow[] = offlineSyncs.data.map((sync) => ({
        id: sync.id,
        status: sync.status,
        cells: [
            sync.course.code,
            `v${sync.packageVersion}`,
            sync.learner,
            sync.county ?? copy.national,
            sync.eventCount,
            new Date(sync.submittedAt).toLocaleString(locale),
            sync.reviewer ?? copy.pending,
            displayValue(copy, sync.status),
        ],
    }));
    const syncPagination: WorkspacePagination = {
        currentPage: offlineSyncs.current_page,
        lastPage: offlineSyncs.last_page,
        perPage: offlineSyncs.per_page,
        total: offlineSyncs.total,
        pageName: 'sync_page',
    };
    const cohortRows: WorkspaceRow[] = cohorts.data.map((cohort) => ({
        id: cohort.id,
        status: cohort.status,
        cells: [
            cohort.code,
            cohort.name,
            `${cohort.course.code} · ${cohort.course.title}`,
            cohort.instructor,
            cohort.county ?? copy.national,
            `${cohort.membersCount} / ${cohort.capacity}`,
            new Date(cohort.startsAt).toLocaleString(locale),
            new Date(cohort.endsAt).toLocaleString(locale),
            displayValue(copy, cohort.status),
        ],
    }));
    const cohortPagination: WorkspacePagination = {
        currentPage: cohorts.current_page,
        lastPage: cohorts.last_page,
        perPage: cohorts.per_page,
        total: cohorts.total,
        pageName: 'cohort_page',
    };

    return (
        <>
            <Head title={copy.page_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.heading}
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                {copy.introduction}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {capabilities.manage && (
                                <>
                                    <CohortForm
                                        courses={options.cohortCourses}
                                        instructors={options.instructors}
                                        counties={options.counties}
                                    />
                                    <ClassroomForm
                                        courses={courses.data}
                                        facilitators={options.facilitators}
                                    />
                                    <CourseForm
                                        sectors={options.sectors}
                                        counties={options.counties}
                                        catalogue={catalogue}
                                    />
                                </>
                            )}
                        </div>
                    </div>
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: copy.status,
                            options: statuses,
                            value: filters.status,
                        },
                    ]}
                />
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">{copy.catalogue}</h2>
                            <p className="text-sm text-muted-foreground">
                                {interpolate(copy.catalogue_count, {
                                    count: courses.total.toLocaleString(locale),
                                })}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <DownloadIcon /> {copy.export}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {['csv', 'xlsx', 'json', 'pdf'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={format}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            workspace:
                                                                'learning',
                                                            format,
                                                        },
                                                        { query: filters },
                                                    )}
                                                >
                                                    {format.toUpperCase()}
                                                </a>
                                            </DropdownMenuItem>
                                        ),
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <GraduationCap className="size-5 text-[#147a55]" />
                        </div>
                    </div>
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                copy.code,
                                copy.course,
                                copy.category,
                                copy.level,
                                copy.scope,
                                copy.reference_release,
                                copy.reference_checksum,
                                copy.content,
                                copy.progress_reach,
                                copy.status,
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
                                workspace: 'learning',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const course = courses.data.find(
                                    (item) => item.id === row.id,
                                );

                                return course ? (
                                    <CourseActions
                                        course={course}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_courses}
                            description={copy.no_courses_description}
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">{copy.cohorts}</h2>
                            <p className="text-sm text-muted-foreground">
                                {interpolate(copy.cohort_count, {
                                    count: cohorts.total.toLocaleString(locale),
                                })}
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon /> {copy.export}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuGroup>
                                    {['csv', 'xlsx', 'json', 'pdf'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={format}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            workspace:
                                                                'learning-cohorts',
                                                            format,
                                                        },
                                                        { query: filters },
                                                    )}
                                                >
                                                    {format.toUpperCase()}
                                                </a>
                                            </DropdownMenuItem>
                                        ),
                                    )}
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    {cohortRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                copy.code,
                                copy.cohort,
                                copy.course,
                                copy.instructor,
                                copy.county,
                                copy.roster,
                                copy.starts,
                                copy.ends,
                                copy.status,
                            ]}
                            rows={cohortRows}
                            pagination={cohortPagination}
                            bulkExport={{
                                workspace: 'learning-cohorts',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const cohort = cohorts.data.find(
                                    (item) => item.id === row.id,
                                );

                                return cohort ? (
                                    <CohortActions
                                        cohort={cohort}
                                        canManage={capabilities.manage}
                                        enrollments={options.cohortEnrollments}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_cohorts}
                            description={copy.no_cohorts_description}
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                {copy.offline_register}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {interpolate(copy.offline_count, {
                                    count: offlineSyncs.total.toLocaleString(locale),
                                })}
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon /> {copy.export}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuGroup>
                                    {['csv', 'xlsx', 'json', 'pdf'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={format}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            workspace:
                                                                'learning-offline-syncs',
                                                            format,
                                                        },
                                                        {
                                                            query: {
                                                                from: filters.from,
                                                                to: filters.to,
                                                                search: filters.search,
                                                            },
                                                        },
                                                    )}
                                                >
                                                    {format.toUpperCase()}
                                                </a>
                                            </DropdownMenuItem>
                                        ),
                                    )}
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    {syncRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                copy.course,
                                copy.package,
                                copy.learner,
                                copy.county,
                                copy.events,
                                copy.submitted,
                                copy.reviewer,
                                copy.status,
                            ]}
                            rows={syncRows}
                            pagination={syncPagination}
                            renderActionControl={(row) => {
                                const sync = offlineSyncs.data.find(
                                    (item) => item.id === row.id,
                                );

                                return sync ? (
                                    <OfflineSyncActions
                                        sync={sync}
                                        canReview={capabilities.review}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_offline_activity}
                            description={copy.no_offline_activity_description}
                            className="min-h-56 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function CohortForm({
    courses,
    instructors,
    counties,
}: {
    courses: Option[];
    instructors: Option[];
    counties: Option[];
}) {
    const copy = usePage().props.localization.learning;

    return (
        <FormSheet
            title={copy.create_cohort}
            description={copy.create_cohort_description}
            triggerLabel={copy.new_cohort}
            icon={GraduationCap}
            size="lg"
        >
            <Form {...storeCohort.form()} className="grid gap-5 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="code"
                                label={copy.cohort_code}
                                error={errors.code}
                            />
                            <Field
                                name="name"
                                label={copy.cohort_name}
                                error={errors.name}
                            />
                            <SearchableSelect
                                id="cohort-course"
                                name="learning_course_id"
                                label={copy.published_course}
                                options={courses}
                                error={errors.learning_course_id}
                            />
                            <SearchableSelect
                                id="cohort-instructor"
                                name="instructor_id"
                                label={copy.accountable_instructor}
                                options={instructors}
                                error={errors.instructor_id}
                            />
                            <SearchableSelect
                                id="cohort-county"
                                name="county_id"
                                label={copy.county_scope}
                                options={counties}
                                optional
                                error={errors.county_id}
                            />
                            <Field
                                name="capacity"
                                label={copy.approved_capacity}
                                type="number"
                                defaultValue="30"
                                error={errors.capacity}
                            />
                            <DatePickerField
                                name="enrollment_opens_on"
                                label={copy.enrollment_opens}
                                required
                                error={errors.enrollment_opens_on}
                            />
                            <DatePickerField
                                name="enrollment_closes_on"
                                label={copy.enrollment_closes}
                                required
                                error={errors.enrollment_closes_on}
                            />
                            <DatePickerField
                                name="starts_at"
                                label={copy.delivery_starts}
                                required
                                includeTime
                                error={errors.starts_at}
                            />
                            <DatePickerField
                                name="ends_at"
                                label={copy.delivery_ends}
                                required
                                includeTime
                                error={errors.ends_at}
                            />
                        </div>
                        <TextField
                            name="description"
                            label={copy.delivery_purpose}
                            error={errors.description}
                        />
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? copy.creating_cohort
                                : copy.create_cohort}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function CohortActions({
    cohort,
    canManage,
    enrollments,
}: {
    cohort: Cohort;
    canManage: boolean;
    enrollments: Option[];
}) {
    const { localization } = usePage().props;
    const copy = localization.learning;
    const [surface, setSurface] = useState<
        'details' | 'member' | 'open' | 'start' | 'complete' | 'cancel' | null
    >(null);
    const transitions =
        {
            draft: ['open', 'cancel'],
            open: ['start', 'cancel'],
            active: ['complete', 'cancel'],
        }[cohort.status] ?? [];

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.cohort_actions, {
                            code: cohort.code,
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye /> {copy.view_cohort}
                        </DropdownMenuItem>
                        {canManage &&
                            ['draft', 'open'].includes(cohort.status) && (
                                <DropdownMenuItem
                                    onSelect={() => setSurface('member')}
                                >
                                    <Plus /> {copy.add_learner}
                                </DropdownMenuItem>
                            )}
                        {canManage &&
                            transitions.map((transitionName) => (
                                <DropdownMenuItem
                                    key={transitionName}
                                    onSelect={() =>
                                        setSurface(
                                            transitionName as
                                                | 'open'
                                                | 'start'
                                                | 'complete'
                                                | 'cancel',
                                        )
                                    }
                                >
                                    {interpolate(copy.transition_cohort, {
                                        transition: displayValue(
                                            copy,
                                            transitionName,
                                        ),
                                    })}
                                </DropdownMenuItem>
                            ))}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? cohort.name
                                : surface === 'member'
                                  ? copy.add_cohort_learner
                                  : interpolate(copy.transition_cohort, {
                                        transition: displayValue(
                                            copy,
                                            surface ?? '',
                                        ),
                                    })}
                        </SheetTitle>
                        <SheetDescription>
                            {cohort.code} {copy.separator}{' '}
                            {cohort.course.code} {copy.separator}{' '}
                            {cohort.instructor}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {displayValue(copy, cohort.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {interpolate(copy.roster_capacity, {
                                            members: cohort.membersCount,
                                            capacity: cohort.capacity,
                                        })}
                                    </Badge>
                                    {cohort.county && (
                                        <CountyIdentity
                                            county={cohort.county}
                                        />
                                    )}
                                </div>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {cohort.description ??
                                        copy.no_delivery_description}
                                </p>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="font-medium">
                                            {copy.enrollment_window}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {new Date(
                                                `${cohort.enrollmentOpensOn}T00:00:00`,
                                            ).toLocaleDateString(
                                                localization.current,
                                            )}{' '}
                                            {copy.range_separator}{' '}
                                            {new Date(
                                                `${cohort.enrollmentClosesOn}T00:00:00`,
                                            ).toLocaleDateString(
                                                localization.current,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            {copy.delivery_window}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {new Date(
                                                cohort.startsAt,
                                            ).toLocaleString(
                                                localization.current,
                                            )}{' '}
                                            {copy.range_separator}{' '}
                                            {new Date(
                                                cohort.endsAt,
                                            ).toLocaleString(
                                                localization.current,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                                <div>
                                    <h3 className="font-medium">
                                        {copy.roster}
                                    </h3>
                                    {cohort.members.length ? (
                                        <ul className="mt-2 flex flex-col gap-2 text-sm text-muted-foreground">
                                            {cohort.members.map((member) => (
                                                <li key={member.id}>
                                                    {member.name}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {copy.no_learners}
                                        </p>
                                    )}
                                </div>
                            </>
                        ) : surface === 'member' ? (
                            <Form
                                {...addCohortMember.form({ cohort: cohort.id })}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <SearchableSelect
                                            id={`cohort-member-${cohort.id}`}
                                            name="learning_enrollment_id"
                                            label={copy.active_enrollment}
                                            options={enrollments}
                                            error={
                                                errors.learning_enrollment_id
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? copy.adding_learner
                                                : copy.add_learner}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface ? (
                            <Form
                                {...transitionCohort.form({
                                    cohort: cohort.id,
                                })}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="transition"
                                            value={surface}
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`cohort-rationale-${cohort.id}`}
                                            >
                                                {copy.lifecycle_rationale}
                                            </Label>
                                            <Textarea
                                                id={`cohort-rationale-${cohort.id}`}
                                                name="rationale"
                                                rows={5}
                                                required
                                                aria-invalid={Boolean(
                                                    errors.rationale,
                                                )}
                                            />
                                            {errors.rationale && (
                                                <p className="text-sm text-destructive">
                                                    {errors.rationale}
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            variant={
                                                surface === 'cancel'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            disabled={processing}
                                        >
                                            {processing
                                                ? copy.updating_cohort
                                                : interpolate(
                                                      copy.transition_cohort,
                                                      {
                                                          transition:
                                                              displayValue(
                                                                  copy,
                                                                  surface,
                                                              ),
                                                      },
                                                  )}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function OfflineSyncActions({
    sync,
    canReview,
}: {
    sync: OfflineSync;
    canReview: boolean;
}) {
    const copy = usePage().props.localization.learning;
    const [surface, setSurface] = useState<
        'details' | 'approve' | 'reject' | null
    >(null);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.offline_actions, {
                            id: sync.clientSyncId,
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye /> {copy.view_evidence}
                        </DropdownMenuItem>
                        {canReview && sync.status === 'pending' && (
                            <>
                                <DropdownMenuItem
                                    onSelect={() => setSurface('approve')}
                                >
                                    <ShieldCheck /> {copy.approve_reconciliation}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setSurface('reject')}
                                >
                                    {copy.reject_reconciliation}
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? copy.offline_evidence
                                : interpolate(copy.offline_progress_action, {
                                      action: displayValue(
                                          copy,
                                          surface ?? '',
                                      ),
                                  })}
                        </SheetTitle>
                        <SheetDescription>
                            {interpolate(copy.offline_package_summary, {
                                code: sync.course.code,
                                version: sync.packageVersion,
                                learner: sync.learner,
                            })}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {displayValue(copy, sync.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {interpolate(copy.event_count, {
                                            count: sync.eventCount,
                                        })}
                                    </Badge>
                                    {sync.county && (
                                        <CountyIdentity county={sync.county} />
                                    )}
                                </div>
                                <dl className="grid gap-3 text-sm">
                                    <div>
                                        <dt className="font-medium">
                                            {copy.client_sync_id}
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.clientSyncId}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            {copy.payload_checksum}
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.payloadChecksum}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            {copy.decision_checksum}
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.decisionChecksum ??
                                                copy.pending}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            {copy.decision_rationale}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {sync.decisionReason ?? copy.pending}
                                        </dd>
                                    </div>
                                </dl>
                            </>
                        ) : surface ? (
                            <Form
                                {...decideOfflineSync.form({
                                    offlineSync: sync.id,
                                })}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="decision"
                                            value={surface}
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`reason-${sync.id}`}
                                            >
                                                {copy.reconciliation_rationale}
                                            </Label>
                                            <Textarea
                                                id={`reason-${sync.id}`}
                                                name="rationale"
                                                rows={5}
                                                required
                                                aria-invalid={Boolean(
                                                    errors.rationale,
                                                )}
                                            />
                                            {errors.rationale && (
                                                <p className="text-sm text-destructive">
                                                    {errors.rationale}
                                                </p>
                                            )}
                                        </div>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {copy.reconciliation_assurance}
                                        </p>
                                        <Button
                                            type="submit"
                                            variant={
                                                surface === 'reject'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            disabled={processing}
                                        >
                                            {processing
                                                ? copy.recording_decision
                                                : interpolate(
                                                      copy.sync_action,
                                                      {
                                                          action: displayValue(
                                                              copy,
                                                              surface,
                                                          ),
                                                      },
                                                  )}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function CourseForm({
    sectors,
    counties,
    catalogue,
}: {
    sectors: Option[];
    counties: Option[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
}) {
    const copy = usePage().props.localization.learning;
    const [lessons, setLessons] = useState([
        { key: 0, type: 'text' },
        { key: 1, type: 'quiz' },
    ]);

    return (
        <FormSheet
            title={copy.create_course}
            description={copy.create_course_description}
            triggerLabel={copy.new_course}
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? interpolate(copy.catalogue_release, {
                          version: catalogue.version ?? '',
                      })
                    : copy.catalogue_required
            }
            icon={Plus}
            size="xl"
        >
            <Form action={storeCourse()} className="grid gap-6 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                name="code"
                                label={copy.course_code}
                                error={errors.code}
                            />
                            <Field
                                name="title"
                                label={copy.course_title}
                                error={errors.title}
                            />
                            <Field
                                name="category"
                                label={copy.category}
                                error={errors.category}
                            />
                            <SearchableSelect
                                id="course-level"
                                name="level"
                                label={copy.level}
                                options={[
                                    'foundation',
                                    'intermediate',
                                    'advanced',
                                ].map((id) => ({
                                    id,
                                    name: displayValue(copy, id),
                                }))}
                                defaultValue="foundation"
                            />
                            <SearchableSelect
                                id="delivery-mode"
                                name="delivery_mode"
                                label={copy.delivery_mode}
                                options={[
                                    'self_paced',
                                    'blended',
                                    'instructor_led',
                                ].map((id) => ({
                                    id,
                                    name: displayValue(copy, id),
                                }))}
                                defaultValue="self_paced"
                            />
                            <SearchableSelect
                                id="course-county"
                                name="county_id"
                                label={copy.county_scope}
                                options={counties}
                                optional
                            />
                            <SearchableSelect
                                id="course-sector"
                                name="sector_id"
                                label={copy.sector}
                                options={sectors}
                                optional
                            />
                            <ReferenceCatalogSelect
                                id="course-language"
                                name="language"
                                label={copy.language}
                                catalog="language"
                            />
                            <Field
                                name="passing_score"
                                label={copy.passing_score}
                                type="number"
                                defaultValue="70"
                            />
                            <Field
                                name="maximum_attempts"
                                label={copy.maximum_attempts}
                                type="number"
                                defaultValue="3"
                            />
                        </div>
                        <TextField
                            name="summary"
                            label={copy.course_summary}
                            error={errors.summary}
                        />
                        <TextField
                            name="description"
                            label={copy.course_description}
                            error={errors.description}
                        />
                        <div className="grid gap-3 rounded-xl border p-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    name="question_bank[selection_count]"
                                    label={copy.questions_per_attempt}
                                    type="number"
                                    defaultValue="1"
                                />
                                <input
                                    type="hidden"
                                    name="question_bank[randomize_questions]"
                                    value="1"
                                />
                                <input
                                    type="hidden"
                                    name="question_bank[randomize_options]"
                                    value="1"
                                />
                                <p className="self-end text-sm text-muted-foreground md:col-span-2">
                                    {copy.variant_assurance}
                                </p>
                            </div>
                            <input
                                type="hidden"
                                name="modules[0][title]"
                                value={copy.core_learning}
                            />
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-semibold">
                                        {copy.core_learning_module}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {copy.lesson_types_description}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setLessons((items) => [
                                            ...items,
                                            {
                                                key:
                                                    Math.max(
                                                        ...items.map(
                                                            (item) => item.key,
                                                        ),
                                                    ) + 1,
                                                type: 'text',
                                            },
                                        ])
                                    }
                                >
                                    {copy.add_lesson}
                                </Button>
                            </div>
                            {lessons.map((lesson, index) => (
                                <Card key={lesson.key}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            {interpolate(copy.lesson_number, {
                                                number: index + 1,
                                            })}
                                        </CardTitle>
                                        {lessons.length > 2 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setLessons((items) =>
                                                        items.filter(
                                                            (item) =>
                                                                item.key !==
                                                                lesson.key,
                                                        ),
                                                    )
                                                }
                                            >
                                                {copy.remove}
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent className="grid gap-4 md:grid-cols-2">
                                        <Field
                                            name={`modules[0][lessons][${index}][title]`}
                                            label={copy.lesson_title}
                                        />
                                        <SearchableSelect
                                            id={`lesson-type-${lesson.key}`}
                                            name={`modules[0][lessons][${index}][content_type]`}
                                            label={copy.content_type}
                                            options={[
                                                'text',
                                                'video',
                                                'audio',
                                                'toolkit',
                                                'manual',
                                                'quiz',
                                            ].map((id) => ({
                                                id,
                                                name: displayValue(copy, id),
                                            }))}
                                            defaultValue={lesson.type}
                                            onValueChange={(value) =>
                                                setLessons((items) =>
                                                    items.map((item) =>
                                                        item.key === lesson.key
                                                            ? {
                                                                  ...item,
                                                                  type: value,
                                                              }
                                                            : item,
                                                    ),
                                                )
                                            }
                                        />
                                        <Field
                                            name={`modules[0][lessons][${index}][estimated_minutes]`}
                                            label={copy.estimated_minutes}
                                            type="number"
                                            defaultValue="10"
                                        />
                                        <Field
                                            name={`modules[0][lessons][${index}][content_url]`}
                                            label={copy.content_url}
                                            optional
                                        />
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label>
                                                {copy.text_content}
                                            </Label>
                                            <Textarea
                                                name={`modules[0][lessons][${index}][content_body]`}
                                                rows={3}
                                            />
                                        </div>
                                        <input
                                            type="hidden"
                                            name={`modules[0][lessons][${index}][is_downloadable]`}
                                            value={
                                                ['toolkit', 'manual'].includes(
                                                    lesson.type,
                                                )
                                                    ? '1'
                                                    : '0'
                                            }
                                        />
                                        {lesson.type === 'quiz' && (
                                            <div className="grid gap-4 rounded-lg border p-3 md:col-span-2">
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][question]`}
                                                    label={copy.quiz_question}
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][options][A]`}
                                                    label={copy.option_a}
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][options][B]`}
                                                    label={copy.option_b}
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][correct_option]`}
                                                    label={copy.correct_option}
                                                    defaultValue="A"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][points]`}
                                                    label={copy.points}
                                                    type="number"
                                                    defaultValue="1"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][variant_group]`}
                                                    label={copy.variant_group}
                                                    defaultValue={`objective-${index + 1}`}
                                                />
                                                <SearchableSelect
                                                    id={`question-difficulty-${lesson.key}`}
                                                    name={`modules[0][lessons][${index}][questions][0][difficulty]`}
                                                    label={copy.difficulty}
                                                    options={[
                                                        {
                                                            id: 'foundation',
                                                            name: copy.foundation,
                                                        },
                                                        {
                                                            id: 'standard',
                                                            name: copy.standard,
                                                        },
                                                        {
                                                            id: 'advanced',
                                                            name: copy.advanced,
                                                        },
                                                    ]}
                                                    defaultValue="standard"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][explanation]`}
                                                    label={copy.explanation}
                                                    optional
                                                />
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <Button type="submit" disabled={processing}>
                            {copy.save_draft_course}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ClassroomForm({
    courses,
    facilitators,
}: {
    courses: Course[];
    facilitators: Option[];
}) {
    const copy = usePage().props.localization.learning;

    return (
        <FormSheet
            title={copy.schedule_virtual_classroom}
            description={copy.schedule_classroom_description}
            triggerLabel={copy.schedule_classroom}
            icon={Video}
        >
            <Form action={storeClassroom()} className="grid gap-4 pt-4">
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="classroom-course"
                            name="learning_course_id"
                            label={copy.course}
                            options={courses.map((course) => ({
                                id: course.id,
                                name: course.title,
                            }))}
                        />
                        <SearchableSelect
                            id="classroom-facilitator"
                            name="facilitator_id"
                            label={copy.facilitator}
                            options={facilitators}
                        />
                        <Field name="title" label={copy.session_title} />
                        <TextField
                            name="description"
                            label={copy.description}
                        />
                        <DatePickerField
                            name="starts_at"
                            label={copy.starts_at}
                            includeTime
                            required
                            error={errors.starts_at}
                        />
                        <DatePickerField
                            name="ends_at"
                            label={copy.ends_at}
                            includeTime
                            required
                            error={errors.ends_at}
                        />
                        <Field
                            name="platform"
                            label={copy.platform}
                            defaultValue="Microsoft Teams"
                        />
                        <Field name="join_url" label={copy.secure_join_url} />
                        <Field
                            name="capacity"
                            label={copy.capacity}
                            type="number"
                            optional
                        />
                        <input type="hidden" name="status" value="scheduled" />
                        <Button type="submit" disabled={processing}>
                            {copy.schedule_classroom}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function CourseActions({
    course,
    capabilities,
}: {
    course: Course;
    capabilities: Props['capabilities'];
}) {
    const copy = usePage().props.localization.learning;
    const [surface, setSurface] = useState<string | null>(null);
    const assetLesson = course.modules
        .flatMap((module) => module.lessons)
        .find((lesson) => surface === `asset:${lesson.id}`);
    const lifecycle = [
        [
            'submit_review',
            copy.submit_quality_review,
            capabilities.manage && course.status === 'draft',
        ],
        [
            'publish',
            copy.publish_course,
            capabilities.review && course.status === 'quality_review',
        ],
        [
            'return',
            copy.return_to_author,
            capabilities.review && course.status === 'quality_review',
        ],
        [
            'retire',
            copy.retire_course,
            capabilities.manage && course.status === 'published',
        ],
    ] as const;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.course_actions, {
                            course: course.title,
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-60">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye />
                        {copy.open_course}
                    </DropdownMenuItem>
                    {!course.enrollment &&
                        capabilities.enroll &&
                        course.status === 'published' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('enroll')}
                            >
                                <BookOpen />
                                {copy.enroll}
                            </DropdownMenuItem>
                        )}
                    {lifecycle
                        .filter(([, , visible]) => visible)
                        .map(([id, label]) => (
                            <DropdownMenuItem
                                key={id}
                                onSelect={() => setSurface(id)}
                            >
                                <GraduationCap />
                                {label}
                            </DropdownMenuItem>
                        ))}
                    {capabilities.manage && course.status === 'published' && (
                        <DropdownMenuItem
                            onSelect={() => setSurface('offline_package')}
                        >
                            <DownloadIcon />
                            {copy.generate_offline_package}
                        </DropdownMenuItem>
                    )}
                    {course.enrollment && course.offlinePackage && (
                        <DropdownMenuItem
                            onSelect={() => setSurface('offline_sync')}
                        >
                            <FileUp />
                            {copy.import_offline_progress}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? course.title
                                : assetLesson
                                  ? interpolate(copy.upload_asset_title, {
                                        lesson: assetLesson.title,
                                    })
                                  : displayValue(copy, surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {course.code} {copy.separator} {course.summary}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {surface === 'details' ? (
                            <CourseDetails
                                course={course}
                                canManageAssets={
                                    capabilities.manage &&
                                    course.status === 'draft'
                                }
                                canAccessAssets={
                                    Boolean(course.enrollment) ||
                                    capabilities.manage ||
                                    capabilities.review
                                }
                                onUploadAsset={(lesson) =>
                                    setSurface(`asset:${lesson.id}`)
                                }
                            />
                        ) : assetLesson ? (
                            <LessonAssetForm
                                course={course}
                                lesson={assetLesson}
                            />
                        ) : surface === 'offline_package' ? (
                            <Form
                                action={generateOfflinePackage({
                                    course: course.id,
                                })}
                                className="grid gap-4 pt-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {copy.offline_package_assurance}
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <DownloadIcon />
                                            {processing
                                                ? copy.generating_package
                                                : copy.generate_verified_package}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface === 'offline_sync' &&
                          course.enrollment &&
                          course.offlinePackage ? (
                            <Form
                                {...submitOfflineSync.form({
                                    enrollment: course.enrollment.id,
                                })}
                                className="grid gap-4 pt-4"
                                resetOnSuccess
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sync-file">
                                                {copy.package_progress_record}
                                            </Label>
                                            <Input
                                                id="sync-file"
                                                name="sync_file"
                                                type="file"
                                                accept="application/json,.json"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.sync_file ||
                                                    errors.payload,
                                                )}
                                            />
                                            {(errors.sync_file ||
                                                errors.payload) && (
                                                <p className="text-sm text-destructive">
                                                    {errors.sync_file ??
                                                        errors.payload}
                                                </p>
                                            )}
                                        </div>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {interpolate(
                                                copy.offline_import_assurance,
                                                {
                                                    version:
                                                        course.offlinePackage
                                                            ?.version ?? '',
                                                },
                                            )}
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <FileUp />
                                            {processing
                                                ? copy.submitting
                                                : copy.submit_reconciliation}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface === 'enroll' ? (
                            <Form action={enroll()} className="grid gap-4 pt-4">
                                <input
                                    type="hidden"
                                    name="learning_course_id"
                                    value={course.id}
                                />
                                <p>
                                    {copy.enroll_confirmation}
                                </p>
                                <Button type="submit">
                                    {copy.confirm_enrolment}
                                </Button>
                            </Form>
                        ) : surface ? (
                            <Form
                                action={transition({ course: course.id })}
                                className="grid gap-4 pt-4"
                            >
                                <input
                                    type="hidden"
                                    name="transition"
                                    value={surface}
                                />
                                <TextField
                                    name="rationale"
                                    label={copy.decision_rationale}
                                />
                                <Button type="submit">
                                    {displayValue(copy, surface)}
                                </Button>
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function CourseDetails({
    course,
    canManageAssets,
    canAccessAssets,
    onUploadAsset,
}: {
    course: Course;
    canManageAssets: boolean;
    canAccessAssets: boolean;
    onUploadAsset: (lesson: Lesson) => void;
}) {
    const { localization } = usePage().props;
    const copy = localization.learning;
    const locale = localization.current;
    const enrollment = course.enrollment;
    const quizQuestions = course.modules
        .flatMap((module) => module.lessons)
        .flatMap((lesson) => lesson.questions);

    return (
        <div className="grid gap-6 pt-4">
            <div className="flex flex-wrap items-center gap-2">
                {course.county && <CountyIdentity county={course.county} />}
                <Badge variant="outline">
                    {displayValue(copy, course.level)}
                </Badge>
                <Badge variant="outline">
                    {interpolate(copy.minutes_count, {
                        count: course.estimatedMinutes,
                    })}
                </Badge>
                <Badge variant="outline">
                    {interpolate(copy.pass_score, {
                        score: course.passingScore,
                    })}
                </Badge>
                {enrollment && (
                    <Badge>
                        {interpolate(copy.progress_complete, {
                            progress: enrollment.progress,
                        })}
                    </Badge>
                )}
            </div>
            <p className="text-sm leading-6 text-muted-foreground">
                {course.description}
            </p>
            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                <p className="font-semibold">{copy.reference_lineage}</p>
                {course.referenceData ? (
                    <p className="mt-1 break-all text-muted-foreground">
                        {interpolate(copy.reference_release_detail, {
                            version: course.referenceData.version,
                            checksum: course.referenceData.checksum,
                        })}
                    </p>
                ) : (
                    <p className="mt-1 text-muted-foreground">
                        {copy.legacy_record_unpinned}
                    </p>
                )}
            </div>
            {course.knowledgeRecommendations.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {copy.recommended_resources}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {course.knowledgeRecommendations.map((item) => (
                            <div
                                key={item.id}
                                className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <p className="font-semibold">
                                        {item.title}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {interpolate(copy.resource_summary, {
                                            reference: item.reference,
                                            type: displayValue(copy, item.type),
                                        })}
                                    </p>
                                    <p className="mt-2 text-sm">
                                        {item.summary}
                                    </p>
                                </div>
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={knowledgeIndex({
                                            query: {
                                                search: item.reference,
                                            },
                                        })}
                                    >
                                        <BookOpen /> {copy.open_resource}
                                    </Link>
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
            {course.offlinePackageAttempt?.status === 'failed' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {copy.offline_generation_failed}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm text-muted-foreground">
                        <p>
                            {interpolate(copy.failed_attempt, {
                                version: course.offlinePackageAttempt.version,
                            })}
                        </p>
                        <p>{course.offlinePackageAttempt.failureMessage}</p>
                    </CardContent>
                </Card>
            )}
            {course.offlinePackage && (
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="text-base">
                                {interpolate(copy.connectivity_package, {
                                    version: course.offlinePackage.version,
                                })}
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {course.offlinePackage.generatedAt
                                    ? new Date(
                                          course.offlinePackage.generatedAt,
                                      ).toLocaleString(locale)
                                    : copy.generation_pending}{' '}
                                {copy.separator}{' '}
                                {course.offlinePackage.sizeBytes
                                    ? interpolate(copy.size_kb, {
                                          size: Math.ceil(
                                              course.offlinePackage.sizeBytes /
                                                  1024,
                                          ).toLocaleString(locale),
                                      })
                                    : copy.size_pending}
                            </p>
                        </div>
                        <Badge variant="outline">
                            {displayValue(copy, course.offlinePackage.status)}
                        </Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <p className="text-xs text-muted-foreground">
                            {copy.package_checksum}{' '}
                            <code className="break-all">
                                {course.offlinePackage.checksum ?? copy.pending}
                            </code>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {copy.offline_progress_notice}
                        </p>
                        {course.offlinePackage.canDownload && (
                            <Button asChild variant="outline" className="w-fit">
                                <a
                                    href={downloadOfflinePackage.url({
                                        offlinePackage:
                                            course.offlinePackage.id,
                                    })}
                                >
                                    <DownloadIcon /> {copy.download_offline_package}
                                </a>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            )}
            {course.classrooms.map((classroom) => (
                <Card key={classroom.id}>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <CalendarDays aria-hidden="true" />
                            <div>
                                <CardTitle className="text-base">
                                    {classroom.title}
                                </CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {new Date(
                                        classroom.startsAt,
                                    ).toLocaleString(locale)}{' '}
                                    {copy.separator}{' '}
                                    {classroom.facilitator} {copy.separator}{' '}
                                    {classroom.platform}
                                </p>
                            </div>
                        </div>
                        <Badge variant="outline">
                            {displayValue(copy, classroom.status)}
                        </Badge>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={classroom.joinUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                {copy.join_classroom}
                            </a>
                        </Button>
                        {classroom.canRecordAttendance && (
                            <Button asChild variant="outline">
                                <Link
                                    href={showClassroom({
                                        classroom: classroom.id,
                                    })}
                                >
                                    {copy.attendance_register}
                                </Link>
                            </Button>
                        )}
                        {classroom.attendance && (
                            <Badge>
                                {interpolate(copy.attendance_summary, {
                                    status: displayValue(
                                        copy,
                                        classroom.attendance.status,
                                    ),
                                    minutes: classroom.attendance.minutes,
                                })}
                            </Badge>
                        )}
                    </CardContent>
                </Card>
            ))}
            <div className="grid gap-4">
                {course.modules.map((module) => (
                    <section key={module.id} className="grid gap-3">
                        <h3 className="font-semibold">{module.title}</h3>
                        {module.lessons.map((lesson) => (
                            <Card key={lesson.id}>
                                <CardHeader className="flex-row items-center justify-between">
                                    <CardTitle className="text-base">
                                        {lesson.title}
                                    </CardTitle>
                                    <Badge variant="outline">
                                        {displayValue(copy, lesson.contentType)}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        {lesson.summary}
                                    </p>
                                    {lesson.contentBody && (
                                        <p className="text-sm leading-6">
                                            {lesson.contentBody}
                                        </p>
                                    )}
                                    {lesson.contentUrl && (
                                        <Button asChild variant="outline">
                                            <a
                                                href={lesson.contentUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {lesson.downloadable
                                                    ? copy.open_resource
                                                    : copy.open_learning_content}
                                            </a>
                                        </Button>
                                    )}
                                    {lesson.assets.map((asset) => (
                                        <div
                                            key={asset.id}
                                            className="grid gap-3 rounded-lg border bg-muted/20 p-3"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {asset.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {displayValue(
                                                            copy,
                                                            asset.sourceType,
                                                        )}{' '}
                                                        {copy.separator}{' '}
                                                        {asset.scanStatus}{' '}
                                                        {copy.separator}{' '}
                                                        {lesson.assetMetadata
                                                            ?.licence
                                                            ? displayValue(
                                                                  copy,
                                                                  lesson
                                                                      .assetMetadata
                                                                      .licence,
                                                              )
                                                            : copy.rights_pending}
                                                    </p>
                                                </div>
                                                {canAccessAssets && (
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <a
                                                                href={previewEvidence.url(
                                                                    {
                                                                        document:
                                                                            asset.id,
                                                                    },
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                <Eye />{' '}
                                                                {copy.preview}
                                                            </a>
                                                        </Button>
                                                        {lesson.downloadable && (
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <a
                                                                    href={downloadEvidence.url(
                                                                        {
                                                                            document:
                                                                                asset.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <DownloadIcon />{' '}
                                                                    {copy.download}
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                            {lesson.assetMetadata
                                                ?.accessible_alternative && (
                                                <p className="text-xs text-muted-foreground">
                                                    {copy.accessible_alternative}{' '}
                                                    {
                                                        lesson.assetMetadata
                                                            .accessible_alternative
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                    {canManageAssets &&
                                        [
                                            'video',
                                            'audio',
                                            'toolkit',
                                            'manual',
                                        ].includes(lesson.contentType) &&
                                        lesson.assets.length === 0 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    onUploadAsset(lesson)
                                                }
                                            >
                                                <Plus />{' '}
                                                {copy.upload_governed_asset}
                                            </Button>
                                        )}
                                    {enrollment &&
                                        lesson.contentType !== 'quiz' &&
                                        !enrollment.completedLessonIds.includes(
                                            lesson.id,
                                        ) && (
                                            <Form
                                                action={complete({
                                                    enrollment: enrollment.id,
                                                    lesson: lesson.id,
                                                })}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="time_spent_seconds"
                                                    value={Math.max(
                                                        60,
                                                        lesson.estimatedMinutes *
                                                            60,
                                                    )}
                                                />
                                                <Button type="submit">
                                                    {copy.mark_lesson_complete}
                                                </Button>
                                            </Form>
                                        )}
                                    {enrollment?.completedLessonIds.includes(
                                        lesson.id,
                                    ) && (
                                        <Badge className="w-fit">
                                            {copy.completed}
                                        </Badge>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                ))}
            </div>
            {enrollment &&
                quizQuestions.length > 0 &&
                enrollment.status !== 'completed' && (
                    <Form
                        action={storeAssessment({ enrollment: enrollment.id })}
                        className="grid gap-4 rounded-xl border p-4"
                    >
                        <h3 className="font-semibold">
                            {copy.course_assessment}
                        </h3>
                        {quizQuestions.map((question) => (
                            <SearchableSelect
                                key={question.id}
                                id={`answer-${question.id}`}
                                name={`answers[${question.id}]`}
                                label={question.question}
                                options={Object.entries(question.options).map(
                                    ([id, name]) => ({ id, name }),
                                )}
                            />
                        ))}
                        <Button type="submit">
                            {copy.submit_assessment}
                        </Button>
                    </Form>
                )}
            {enrollment?.certificate && (
                <Button asChild>
                    <a
                        href={showCertificate.url({
                            certificate: enrollment.certificate.id,
                        })}
                        target="_blank"
                    >
                        <Award />
                        {copy.preview_certificate}
                    </a>
                </Button>
            )}
        </div>
    );
}

function LessonAssetForm({
    course,
    lesson,
}: {
    course: Course;
    lesson: Lesson;
}) {
    const copy = usePage().props.localization.learning;
    const isMedia = ['video', 'audio'].includes(lesson.contentType);

    return (
        <Form
            {...storeLessonAsset.form({ course: course.id, lesson: lesson.id })}
            className="grid gap-5 pt-4"
            resetOnSuccess
        >
            {({ errors, processing }) => (
                <>
                    <p className="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                        {copy.asset_security_notice}
                    </p>
                    <Field
                        name="title"
                        label={copy.asset_title}
                        defaultValue={interpolate(copy.asset_default_title, {
                            lesson: lesson.title,
                        })}
                        error={errors.title}
                    />
                    <SearchableSelect
                        id={`asset-source-${lesson.id}`}
                        name="source_type"
                        label={copy.source_type}
                        options={(isMedia
                            ? ['digital']
                            : ['digital', 'scanned']
                        ).map((id) => ({
                            id,
                            name: displayValue(copy, id),
                        }))}
                        defaultValue="digital"
                        error={errors.source_type}
                    />
                    <Field
                        name="rights_holder"
                        label={copy.rights_holder}
                        defaultValue={copy.department_name}
                        error={errors.rights_holder}
                    />
                    <SearchableSelect
                        id={`asset-licence-${lesson.id}`}
                        name="licence"
                        label={copy.licence_basis}
                        options={[
                            'government_open',
                            'permission_granted',
                            'third_party_restricted',
                            'internal_training',
                        ].map((id) => ({
                            id,
                            name: displayValue(copy, id),
                        }))}
                        defaultValue="government_open"
                        error={errors.licence}
                    />
                    <TextField
                        name="accessible_alternative"
                        label={copy.accessible_text_alternative}
                        error={errors.accessible_alternative}
                    />
                    <div className="grid gap-3 rounded-lg border p-3">
                        <input
                            type="hidden"
                            name="transcript_available"
                            value="0"
                        />
                        <label className="flex items-center gap-3 text-sm">
                            <Checkbox
                                name="transcript_available"
                                value="1"
                                defaultChecked={isMedia}
                            />
                            {copy.transcript_available}
                        </label>
                        <input type="hidden" name="is_downloadable" value="0" />
                        <label className="flex items-center gap-3 text-sm">
                            <Checkbox
                                name="is_downloadable"
                                value="1"
                                defaultChecked={['toolkit', 'manual'].includes(
                                    lesson.contentType,
                                )}
                            />
                            {copy.permit_download}
                        </label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`asset-file-${lesson.id}`}>
                            {copy.learning_asset}
                        </Label>
                        <Input
                            id={`asset-file-${lesson.id}`}
                            name="document"
                            type="file"
                            required
                            accept={
                                lesson.contentType === 'video'
                                    ? '.mp4,.webm'
                                    : lesson.contentType === 'audio'
                                      ? '.mp3,.wav,.m4a'
                                      : '.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg'
                            }
                            aria-invalid={Boolean(errors.document)}
                        />
                        {errors.document && (
                            <p
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {errors.document}
                            </p>
                        )}
                    </div>
                    <Button type="submit" disabled={processing}>
                        {copy.upload_private_asset}
                    </Button>
                </>
            )}
        </Form>
    );
}

function Field({
    name,
    label,
    type = 'text',
    defaultValue,
    optional = false,
    error,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    defaultValue?: string;
    optional?: boolean;
    error?: string;
}) {
    const id = name.replaceAll(/[^a-zA-Z0-9_-]/g, '-');

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type={type}
                defaultValue={defaultValue}
                step={type === 'number' ? '0.01' : undefined}
                required={!optional}
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
function TextField({
    name,
    label,
    error,
}: {
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                rows={4}
                required
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
function humanize(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function displayValue(copy: Record<string, string>, value: string): string {
    return copy[`value_${value}`] ?? humanize(value);
}
