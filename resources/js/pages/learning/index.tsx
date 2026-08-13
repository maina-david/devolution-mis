import { Form, Head, Link } from '@inertiajs/react';
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
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
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
const statuses = [
    'draft',
    'quality_review',
    'published',
    'retired',
    'open',
    'active',
    'completed',
    'cancelled',
].map((id) => ({ id, name: humanize(id) }));

export default function Learning({
    courses,
    cohorts,
    offlineSyncs,
    filters,
    capabilities,
    catalogue,
    options,
}: Props) {
    const rows: WorkspaceRow[] = courses.data.map((course) => ({
        id: course.id,
        status: course.status,
        cells: [
            course.code,
            course.title,
            course.category,
            course.level,
            course.county ?? 'National',
            course.referenceData
                ? `v${course.referenceData.version}`
                : 'Legacy · unpinned',
            course.referenceData?.checksum ?? 'Legacy · unpinned',
            `${course.moduleCount} modules · ${course.estimatedMinutes} min`,
            course.enrollment
                ? `${course.enrollment.progress}%`
                : `${course.enrollmentCount} enrolled`,
            humanize(course.status),
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
            sync.county ?? 'National',
            sync.eventCount,
            new Date(sync.submittedAt).toLocaleString(DEFAULT_LOCALE),
            sync.reviewer ?? 'Pending',
            humanize(sync.status),
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
            cohort.county ?? 'National',
            `${cohort.membersCount} / ${cohort.capacity}`,
            new Date(cohort.startsAt).toLocaleString(DEFAULT_LOCALE),
            new Date(cohort.endsAt).toLocaleString(DEFAULT_LOCALE),
            humanize(cohort.status),
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
            <Head title="E-Learning" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Continuous professional development
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Devolution learning hub
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Multimedia courses, practical toolkits, live
                                classrooms, tracked progress, assessments, and
                                verifiable certification.
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
                            label: 'Status',
                            options: statuses,
                            value: filters.status,
                        },
                    ]}
                />
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">Learning catalogue</h2>
                            <p className="text-sm text-muted-foreground">
                                {courses.total.toLocaleString()} courses in your
                                authorized catalogue
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <DownloadIcon /> Export
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
                                'Code',
                                'Course',
                                'Category',
                                'Level',
                                'Scope',
                                'Reference release',
                                'Reference checksum',
                                'Content',
                                'Progress / reach',
                                'Status',
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
                            title="No matching courses"
                            description="Adjust the filters or publish the first governed multimedia learning course."
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">Learning cohorts</h2>
                            <p className="text-sm text-muted-foreground">
                                {cohorts.total.toLocaleString()} instructor-led
                                delivery cohorts in your authorized scope
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon /> Export
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
                                'Code',
                                'Cohort',
                                'Course',
                                'Instructor',
                                'County',
                                'Roster',
                                'Starts',
                                'Ends',
                                'Status',
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
                            title="No matching learning cohorts"
                            description="Create a course-bound cohort to assign an accountable instructor, delivery window and capacity-governed learner roster."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                Offline synchronization register
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {offlineSyncs.total.toLocaleString()} governed
                                submission and reconciliation records
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon /> Export
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
                                'Course',
                                'Package',
                                'Learner',
                                'County',
                                'Events',
                                'Submitted',
                                'Reviewer',
                                'Status',
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
                            title="No offline activity submitted"
                            description="Downloaded course activity will appear here after a learner imports the package-generated progress record."
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
    return (
        <FormSheet
            title="Create learning cohort"
            description="Bind a published course to an accountable instructor, county scope, capacity and governed delivery window."
            triggerLabel="New cohort"
            icon={GraduationCap}
            size="lg"
        >
            <Form {...storeCohort.form()} className="grid gap-5 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="code"
                                label="Cohort code"
                                error={errors.code}
                            />
                            <Field
                                name="name"
                                label="Cohort name"
                                error={errors.name}
                            />
                            <SearchableSelect
                                id="cohort-course"
                                name="learning_course_id"
                                label="Published course"
                                options={courses}
                                error={errors.learning_course_id}
                            />
                            <SearchableSelect
                                id="cohort-instructor"
                                name="instructor_id"
                                label="Accountable instructor"
                                options={instructors}
                                error={errors.instructor_id}
                            />
                            <SearchableSelect
                                id="cohort-county"
                                name="county_id"
                                label="County scope"
                                options={counties}
                                optional
                                error={errors.county_id}
                            />
                            <Field
                                name="capacity"
                                label="Approved capacity"
                                type="number"
                                defaultValue="30"
                                error={errors.capacity}
                            />
                            <DatePickerField
                                name="enrollment_opens_on"
                                label="Enrollment opens"
                                required
                                error={errors.enrollment_opens_on}
                            />
                            <DatePickerField
                                name="enrollment_closes_on"
                                label="Enrollment closes"
                                required
                                error={errors.enrollment_closes_on}
                            />
                            <DatePickerField
                                name="starts_at"
                                label="Delivery starts"
                                required
                                includeTime
                                error={errors.starts_at}
                            />
                            <DatePickerField
                                name="ends_at"
                                label="Delivery ends"
                                required
                                includeTime
                                error={errors.ends_at}
                            />
                        </div>
                        <TextField
                            name="description"
                            label="Delivery purpose and audience"
                            error={errors.description}
                        />
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating cohort…' : 'Create cohort'}
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
                        aria-label={`Actions for cohort ${cohort.code}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye /> View cohort
                        </DropdownMenuItem>
                        {canManage &&
                            ['draft', 'open'].includes(cohort.status) && (
                                <DropdownMenuItem
                                    onSelect={() => setSurface('member')}
                                >
                                    <Plus /> Add learner
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
                                    {humanize(transitionName)} cohort
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
                                  ? 'Add cohort learner'
                                  : `${humanize(surface ?? '')} cohort`}
                        </SheetTitle>
                        <SheetDescription>
                            {cohort.code} · {cohort.course.code} ·{' '}
                            {cohort.instructor}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {humanize(cohort.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {cohort.membersCount} /{' '}
                                        {cohort.capacity} learners
                                    </Badge>
                                    {cohort.county && (
                                        <CountyIdentity
                                            county={cohort.county}
                                        />
                                    )}
                                </div>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {cohort.description ??
                                        'No delivery description recorded.'}
                                </p>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="font-medium">
                                            Enrollment window
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {new Date(
                                                `${cohort.enrollmentOpensOn}T00:00:00`,
                                            ).toLocaleDateString(
                                                DEFAULT_LOCALE,
                                            )}{' '}
                                            –{' '}
                                            {new Date(
                                                `${cohort.enrollmentClosesOn}T00:00:00`,
                                            ).toLocaleDateString(
                                                DEFAULT_LOCALE,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            Delivery window
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {new Date(
                                                cohort.startsAt,
                                            ).toLocaleString(
                                                DEFAULT_LOCALE,
                                            )}{' '}
                                            –{' '}
                                            {new Date(
                                                cohort.endsAt,
                                            ).toLocaleString(DEFAULT_LOCALE)}
                                        </dd>
                                    </div>
                                </dl>
                                <div>
                                    <h3 className="font-medium">Roster</h3>
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
                                            No learners assigned yet.
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
                                            label="Active course enrollment"
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
                                                ? 'Adding learner…'
                                                : 'Add learner'}
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
                                                Lifecycle rationale
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
                                                ? 'Updating cohort…'
                                                : `${humanize(surface)} cohort`}
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
                        aria-label={`Actions for offline synchronization ${sync.clientSyncId}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye /> View evidence
                        </DropdownMenuItem>
                        {canReview && sync.status === 'pending' && (
                            <>
                                <DropdownMenuItem
                                    onSelect={() => setSurface('approve')}
                                >
                                    <ShieldCheck /> Approve reconciliation
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setSurface('reject')}
                                >
                                    Reject reconciliation
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
                                ? 'Offline synchronization evidence'
                                : `${humanize(surface ?? '')} offline progress`}
                        </SheetTitle>
                        <SheetDescription>
                            {sync.course.code} · package v{sync.packageVersion}{' '}
                            · {sync.learner}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {humanize(sync.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {sync.eventCount} events
                                    </Badge>
                                    {sync.county && (
                                        <CountyIdentity county={sync.county} />
                                    )}
                                </div>
                                <dl className="grid gap-3 text-sm">
                                    <div>
                                        <dt className="font-medium">
                                            Client synchronization ID
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.clientSyncId}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            Payload SHA-256
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.payloadChecksum}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            Decision SHA-256
                                        </dt>
                                        <dd className="break-all text-muted-foreground">
                                            {sync.decisionChecksum ?? 'Pending'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            Decision rationale
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {sync.decisionReason ?? 'Pending'}
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
                                                Reconciliation rationale
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
                                            Approval verifies the retained
                                            package and payload checksums,
                                            rejects stale progress, and applies
                                            only monotonic non-quiz activity.
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
                                                ? 'Recording decision…'
                                                : `${humanize(surface)} synchronization`}
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
    const [lessons, setLessons] = useState([
        { key: 0, type: 'text' },
        { key: 1, type: 'quiz' },
    ]);

    return (
        <FormSheet
            title="Create learning course"
            description="Build multimedia content and an interactive knowledge check before independent publication."
            triggerLabel="New course"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'A checksum-verified, effective reference-data release is required.'
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
                                label="Course code"
                                error={errors.code}
                            />
                            <Field
                                name="title"
                                label="Course title"
                                error={errors.title}
                            />
                            <Field
                                name="category"
                                label="Category"
                                error={errors.category}
                            />
                            <SearchableSelect
                                id="course-level"
                                name="level"
                                label="Level"
                                options={[
                                    'foundation',
                                    'intermediate',
                                    'advanced',
                                ].map((id) => ({ id, name: humanize(id) }))}
                                defaultValue="foundation"
                            />
                            <SearchableSelect
                                id="delivery-mode"
                                name="delivery_mode"
                                label="Delivery mode"
                                options={[
                                    'self_paced',
                                    'blended',
                                    'instructor_led',
                                ].map((id) => ({ id, name: humanize(id) }))}
                                defaultValue="self_paced"
                            />
                            <SearchableSelect
                                id="course-county"
                                name="county_id"
                                label="County scope"
                                options={counties}
                                optional
                            />
                            <SearchableSelect
                                id="course-sector"
                                name="sector_id"
                                label="Sector"
                                options={sectors}
                                optional
                            />
                            <ReferenceCatalogSelect
                                id="course-language"
                                name="language"
                                label="Language"
                                catalog="language"
                            />
                            <Field
                                name="passing_score"
                                label="Passing score (%)"
                                type="number"
                                defaultValue="70"
                            />
                            <Field
                                name="maximum_attempts"
                                label="Maximum attempts"
                                type="number"
                                defaultValue="3"
                            />
                        </div>
                        <TextField
                            name="summary"
                            label="Course summary"
                            error={errors.summary}
                        />
                        <TextField
                            name="description"
                            label="Course description"
                            error={errors.description}
                        />
                        <div className="grid gap-3 rounded-xl border p-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    name="question_bank[selection_count]"
                                    label="Questions per attempt"
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
                                    Quiz variants are selected reproducibly,
                                    one question per objective group, with a
                                    checksum retained on every attempt.
                                </p>
                            </div>
                            <input
                                type="hidden"
                                name="modules[0][title]"
                                value="Core learning"
                            />
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-semibold">
                                        Core learning module
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Add text, video, audio, toolkit, manual,
                                        and quiz lessons.
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
                                    Add lesson
                                </Button>
                            </div>
                            {lessons.map((lesson, index) => (
                                <Card key={lesson.key}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            Lesson {index + 1}
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
                                                Remove
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent className="grid gap-4 md:grid-cols-2">
                                        <Field
                                            name={`modules[0][lessons][${index}][title]`}
                                            label="Lesson title"
                                        />
                                        <SearchableSelect
                                            id={`lesson-type-${lesson.key}`}
                                            name={`modules[0][lessons][${index}][content_type]`}
                                            label="Content type"
                                            options={[
                                                'text',
                                                'video',
                                                'audio',
                                                'toolkit',
                                                'manual',
                                                'quiz',
                                            ].map((id) => ({
                                                id,
                                                name: humanize(id),
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
                                            label="Estimated minutes"
                                            type="number"
                                            defaultValue="10"
                                        />
                                        <Field
                                            name={`modules[0][lessons][${index}][content_url]`}
                                            label="Content or repository URL"
                                            optional
                                        />
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label>
                                                Text content / description
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
                                                    label="Quiz question"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][options][A]`}
                                                    label="Option A"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][options][B]`}
                                                    label="Option B"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][correct_option]`}
                                                    label="Correct option key"
                                                    defaultValue="A"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][points]`}
                                                    label="Points"
                                                    type="number"
                                                    defaultValue="1"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][variant_group]`}
                                                    label="Objective / variant group"
                                                    defaultValue={`objective-${index + 1}`}
                                                />
                                                <SearchableSelect
                                                    id={`question-difficulty-${lesson.key}`}
                                                    name={`modules[0][lessons][${index}][questions][0][difficulty]`}
                                                    label="Difficulty"
                                                    options={[
                                                        {
                                                            id: 'foundation',
                                                            name: 'Foundation',
                                                        },
                                                        {
                                                            id: 'standard',
                                                            name: 'Standard',
                                                        },
                                                        {
                                                            id: 'advanced',
                                                            name: 'Advanced',
                                                        },
                                                    ]}
                                                    defaultValue="standard"
                                                />
                                                <Field
                                                    name={`modules[0][lessons][${index}][questions][0][explanation]`}
                                                    label="Explanation"
                                                    optional
                                                />
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <Button type="submit" disabled={processing}>
                            Save draft course
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
    return (
        <FormSheet
            title="Schedule virtual classroom"
            description="Register a governed live webinar or workshop link."
            triggerLabel="Schedule classroom"
            icon={Video}
        >
            <Form action={storeClassroom()} className="grid gap-4 pt-4">
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="classroom-course"
                            name="learning_course_id"
                            label="Course"
                            options={courses.map((course) => ({
                                id: course.id,
                                name: course.title,
                            }))}
                        />
                        <SearchableSelect
                            id="classroom-facilitator"
                            name="facilitator_id"
                            label="Facilitator"
                            options={facilitators}
                        />
                        <Field name="title" label="Session title" />
                        <TextField name="description" label="Description" />
                        <DatePickerField
                            name="starts_at"
                            label="Starts at"
                            includeTime
                            required
                            error={errors.starts_at}
                        />
                        <DatePickerField
                            name="ends_at"
                            label="Ends at"
                            includeTime
                            required
                            error={errors.ends_at}
                        />
                        <Field
                            name="platform"
                            label="Platform"
                            defaultValue="Microsoft Teams"
                        />
                        <Field name="join_url" label="Secure join URL" />
                        <Field
                            name="capacity"
                            label="Capacity"
                            type="number"
                            optional
                        />
                        <input type="hidden" name="status" value="scheduled" />
                        <Button type="submit" disabled={processing}>
                            Schedule classroom
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
    const [surface, setSurface] = useState<string | null>(null);
    const assetLesson = course.modules
        .flatMap((module) => module.lessons)
        .find((lesson) => surface === `asset:${lesson.id}`);
    const lifecycle = [
        [
            'submit_review',
            'Submit quality review',
            capabilities.manage && course.status === 'draft',
        ],
        [
            'publish',
            'Publish course',
            capabilities.review && course.status === 'quality_review',
        ],
        [
            'return',
            'Return to author',
            capabilities.review && course.status === 'quality_review',
        ],
        [
            'retire',
            'Retire course',
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
                        aria-label={`Actions for ${course.title}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-60">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye />
                        Open course
                    </DropdownMenuItem>
                    {!course.enrollment &&
                        capabilities.enroll &&
                        course.status === 'published' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('enroll')}
                            >
                                <BookOpen />
                                Enroll
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
                            Generate offline package
                        </DropdownMenuItem>
                    )}
                    {course.enrollment && course.offlinePackage && (
                        <DropdownMenuItem
                            onSelect={() => setSurface('offline_sync')}
                        >
                            <FileUp />
                            Import offline progress
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
                                  ? `Upload asset · ${assetLesson.title}`
                                  : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {course.code} · {course.summary}
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
                                            Generate an immutable ZIP containing
                                            structured course content,
                                            accessible alternatives, a
                                            checksum-bound manifest, and only
                                            clean assets approved for download.
                                            Assessment answer keys and online
                                            progress records are excluded.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <DownloadIcon />
                                            {processing
                                                ? 'Generating package…'
                                                : 'Generate verified package'}
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
                                                Package progress record
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
                                            Upload the JSON record exported by
                                            package v
                                            {course.offlinePackage?.version}.
                                            Its package checksum, lesson scope,
                                            timestamps and replay identifier
                                            will be verified before a separate
                                            reviewer can reconcile progress.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <FileUp />
                                            {processing
                                                ? 'Submitting…'
                                                : 'Submit for reconciliation'}
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
                                    Enroll in this course and begin tracked
                                    learning?
                                </p>
                                <Button type="submit">Confirm enrolment</Button>
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
                                    label="Decision rationale"
                                />
                                <Button type="submit">
                                    {humanize(surface)}
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
    const enrollment = course.enrollment;
    const quizQuestions = course.modules
        .flatMap((module) => module.lessons)
        .flatMap((lesson) => lesson.questions);

    return (
        <div className="grid gap-6 pt-4">
            <div className="flex flex-wrap items-center gap-2">
                {course.county && <CountyIdentity county={course.county} />}
                <Badge variant="outline">{humanize(course.level)}</Badge>
                <Badge variant="outline">
                    {course.estimatedMinutes} minutes
                </Badge>
                <Badge variant="outline">Pass {course.passingScore}%</Badge>
                {enrollment && <Badge>{enrollment.progress}% complete</Badge>}
            </div>
            <p className="text-sm leading-6 text-muted-foreground">
                {course.description}
            </p>
            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                <p className="font-semibold">Reference-data lineage</p>
                {course.referenceData ? (
                    <p className="mt-1 break-all text-muted-foreground">
                        Release v{course.referenceData.version} ·{' '}
                        {course.referenceData.checksum}
                    </p>
                ) : (
                    <p className="mt-1 text-muted-foreground">
                        Legacy record · unpinned
                    </p>
                )}
            </div>
            {course.knowledgeRecommendations.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recommended knowledge resources
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
                                        {item.reference} · {humanize(item.type)}
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
                                        <BookOpen /> Open resource
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
                            Offline package generation failed
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm text-muted-foreground">
                        <p>
                            Attempt v{course.offlinePackageAttempt.version}{' '}
                            failed closed. The last verified package remains
                            available.
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
                                Constrained-connectivity package v
                                {course.offlinePackage.version}
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {course.offlinePackage.generatedAt
                                    ? new Date(
                                          course.offlinePackage.generatedAt,
                                      ).toLocaleString(DEFAULT_LOCALE)
                                    : 'Generation pending'}{' '}
                                ·{' '}
                                {course.offlinePackage.sizeBytes
                                    ? `${Math.ceil(course.offlinePackage.sizeBytes / 1024).toLocaleString()} KB`
                                    : 'Size pending'}
                            </p>
                        </div>
                        <Badge variant="outline">
                            {humanize(course.offlinePackage.status)}
                        </Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <p className="text-xs text-muted-foreground">
                            Package SHA-256:{' '}
                            <code className="break-all">
                                {course.offlinePackage.checksum ?? 'Pending'}
                            </code>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Offline activity does not update the official
                            learning record until the learner returns to an
                            authorized IDMIS session.
                        </p>
                        {course.offlinePackage.canDownload && (
                            <Button asChild variant="outline" className="w-fit">
                                <a
                                    href={downloadOfflinePackage.url({
                                        offlinePackage:
                                            course.offlinePackage.id,
                                    })}
                                >
                                    <DownloadIcon /> Download offline package
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
                                    ).toLocaleString(DEFAULT_LOCALE)}{' '}
                                    · {classroom.facilitator} ·{' '}
                                    {classroom.platform}
                                </p>
                            </div>
                        </div>
                        <Badge variant="outline">
                            {humanize(classroom.status)}
                        </Badge>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={classroom.joinUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Join classroom
                            </a>
                        </Button>
                        {classroom.canRecordAttendance && (
                            <Button asChild variant="outline">
                                <Link
                                    href={showClassroom({
                                        classroom: classroom.id,
                                    })}
                                >
                                    Attendance register
                                </Link>
                            </Button>
                        )}
                        {classroom.attendance && (
                            <Badge>
                                {humanize(classroom.attendance.status)} ·{' '}
                                {classroom.attendance.minutes} minutes
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
                                        {humanize(lesson.contentType)}
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
                                                    ? 'Open resource'
                                                    : 'Open learning content'}
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
                                                        {humanize(
                                                            asset.sourceType,
                                                        )}{' '}
                                                        · {asset.scanStatus} ·{' '}
                                                        {lesson.assetMetadata
                                                            ?.licence
                                                            ? humanize(
                                                                  lesson
                                                                      .assetMetadata
                                                                      .licence,
                                                              )
                                                            : 'Rights pending'}
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
                                                                <Eye /> Preview
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
                                                                    Download
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                            {lesson.assetMetadata
                                                ?.accessible_alternative && (
                                                <p className="text-xs text-muted-foreground">
                                                    Accessible alternative:{' '}
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
                                                <Plus /> Upload governed asset
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
                                                    Mark lesson complete
                                                </Button>
                                            </Form>
                                        )}
                                    {enrollment?.completedLessonIds.includes(
                                        lesson.id,
                                    ) && (
                                        <Badge className="w-fit">
                                            Completed
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
                        <h3 className="font-semibold">Course assessment</h3>
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
                        <Button type="submit">Submit assessment</Button>
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
                        Preview certificate
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
                        The asset is stored privately, malware-scanned,
                        checksummed and available only to authorized course
                        managers, reviewers and enrolled learners.
                    </p>
                    <Field
                        name="title"
                        label="Asset title"
                        defaultValue={`${lesson.title} asset`}
                        error={errors.title}
                    />
                    <SearchableSelect
                        id={`asset-source-${lesson.id}`}
                        name="source_type"
                        label="Source type"
                        options={(isMedia
                            ? ['digital']
                            : ['digital', 'scanned']
                        ).map((id) => ({ id, name: humanize(id) }))}
                        defaultValue="digital"
                        error={errors.source_type}
                    />
                    <Field
                        name="rights_holder"
                        label="Rights holder"
                        defaultValue="State Department for Devolution"
                        error={errors.rights_holder}
                    />
                    <SearchableSelect
                        id={`asset-licence-${lesson.id}`}
                        name="licence"
                        label="Licence / usage basis"
                        options={[
                            'government_open',
                            'permission_granted',
                            'third_party_restricted',
                            'internal_training',
                        ].map((id) => ({ id, name: humanize(id) }))}
                        defaultValue="government_open"
                        error={errors.licence}
                    />
                    <TextField
                        name="accessible_alternative"
                        label="Accessible text alternative"
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
                            Transcript or equivalent is available
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
                            Permit authorized enrolled learners to download
                        </label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`asset-file-${lesson.id}`}>
                            Learning asset
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
                        Upload private learning asset
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
