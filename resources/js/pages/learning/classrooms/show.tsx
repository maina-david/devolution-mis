import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, DownloadIcon, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
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
import { index as learningIndex } from '@/routes/learning';
import { store as storeAttendance } from '@/routes/learning/classrooms/attendance';
import { exportMethod } from '@/routes/workspace';

type Classroom = {
    id: string;
    title: string;
    course: {
        id: string;
        code: string;
        title: string;
        county: CountyIdentityValue | null;
    };
    facilitator: string;
    startsAt: string;
    endsAt: string;
    platform: string;
    capacity: number | null;
    status: string;
};

type AttendanceRow = WorkspaceRow & {
    meta?: {
        userName?: string | null;
        joinedAt?: string | null;
        leftAt?: string | null;
        source?: string | null;
        providerEventId?: string | null;
        notes?: string | null;
    };
};

type Props = {
    classroom: Classroom;
    roster: { rows: AttendanceRow[]; pagination: WorkspacePagination };
    filters: Record<string, string | undefined>;
};

export default function ClassroomAttendance({
    classroom,
    roster,
    filters,
}: Props) {
    const { localization } = usePage().props;
    const copy = localization.learning;
    const locale = localization.current;
    const attendanceStatuses = attendanceStatusOptions(copy);
    const exportQuery = { ...filters, classroom_id: classroom.id };

    return (
        <>
            <Head
                title={interpolate(copy.classroom_attendance_title, {
                    title: classroom.title,
                })}
            />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <Button
                                asChild
                                variant="link"
                                className="mb-3 h-auto p-0 text-[#83d4ad]"
                            >
                                <Link href={learningIndex()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {copy.learning_hub}
                                </Link>
                            </Button>
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                {copy.governed_attendance_register}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {classroom.title}
                            </h1>
                            <p className="mt-3 text-[#c7d6dd]">
                                {classroom.course.code}{' '}
                                {copy.metadata_separator}{' '}
                                {classroom.course.title}{' '}
                                {copy.metadata_separator}{' '}
                                {classroom.facilitator}{' '}
                                {copy.metadata_separator} {classroom.platform}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {classroom.course.county && (
                                <CountyIdentity
                                    county={classroom.course.county}
                                    className="rounded-lg bg-white px-3 py-2 text-[#12304a]"
                                />
                            )}
                            <Badge variant="secondary">
                                {copy[`value_${classroom.status}`] ??
                                    humanize(classroom.status)}
                            </Badge>
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
                            label: copy.attendance_status,
                            options: attendanceStatuses,
                            value: filters.status,
                        },
                    ]}
                />

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                {copy.enrolled_learner_roster}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {interpolate(copy.roster_summary, {
                                    count: roster.pagination.total.toLocaleString(
                                        locale,
                                    ),
                                    start: new Date(
                                        classroom.startsAt,
                                    ).toLocaleString(locale),
                                    end: new Date(
                                        classroom.endsAt,
                                    ).toLocaleString(locale),
                                })}
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon aria-hidden="true" />{' '}
                                    {copy.export}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {['csv', 'xlsx', 'json', 'pdf'].map(
                                    (format) => (
                                        <DropdownMenuItem key={format} asChild>
                                            <a
                                                href={exportMethod.url(
                                                    {
                                                        workspace:
                                                            'learning-attendance',
                                                        format,
                                                    },
                                                    { query: exportQuery },
                                                )}
                                            >
                                                {format.toUpperCase()}
                                            </a>
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    {roster.rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                copy.learner,
                                copy.county,
                                copy.enrolment,
                                copy.joined,
                                copy.left,
                                copy.minutes,
                                copy.source,
                                copy.recorded_by,
                                copy.recorded_at,
                                copy.attendance,
                            ]}
                            rows={roster.rows}
                            pagination={roster.pagination}
                            renderActionControl={(row) => (
                                <AttendanceAction
                                    classroom={classroom}
                                    row={row as AttendanceRow}
                                />
                            )}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_matching_learners}
                            description={copy.adjust_or_enroll}
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function AttendanceAction({
    classroom,
    row,
}: {
    classroom: Classroom;
    row: AttendanceRow;
}) {
    const copy = usePage().props.localization.learning;
    const attendanceStatuses = attendanceStatusOptions(copy);
    const [open, setOpen] = useState(false);
    const [status, setStatus] = useState(
        row.status === 'not_recorded' ? 'present' : (row.status ?? 'present'),
    );
    const [source, setSource] = useState(row.meta?.source ?? 'manual');
    const isAmendment = row.status !== 'not_recorded';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.attendance_actions, {
                            learner: row.meta?.userName ?? copy.learner,
                        })}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        {copy.record_attendance}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {isAmendment ? copy.amend : copy.record}{' '}
                            {copy.attendance}
                        </SheetTitle>
                        <SheetDescription>
                            {interpolate(copy.attendance_schedule_assurance, {
                                learner: row.meta?.userName ?? copy.learner,
                            })}
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        action={storeAttendance({ classroom: classroom.id })}
                        className="grid gap-4 px-4 pb-8"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="learning_enrollment_id"
                                    value={row.id}
                                />
                                <SearchableSelect
                                    id={`attendance-status-${row.id}`}
                                    name="attendance_status"
                                    label={copy.attendance_status}
                                    options={attendanceStatuses.filter(
                                        (option) =>
                                            option.id !== 'not_recorded',
                                    )}
                                    defaultValue={status}
                                    onValueChange={setStatus}
                                />
                                {status !== 'absent' && (
                                    <>
                                        <DatePickerField
                                            name="joined_at"
                                            label={copy.joined_at}
                                            includeTime
                                            required
                                            defaultValue={
                                                row.meta?.joinedAt ??
                                                classroom.startsAt
                                            }
                                            error={errors.joined_at}
                                        />
                                        <DatePickerField
                                            name="left_at"
                                            label={copy.left_at}
                                            includeTime
                                            required
                                            defaultValue={
                                                row.meta?.leftAt ??
                                                classroom.endsAt
                                            }
                                            error={errors.left_at}
                                        />
                                    </>
                                )}
                                <SearchableSelect
                                    id={`attendance-source-${row.id}`}
                                    name="source"
                                    label={copy.evidence_source}
                                    options={[
                                        { id: 'manual', name: copy.manual },
                                        {
                                            id: 'provider_import',
                                            name: copy.provider_import,
                                        },
                                    ]}
                                    defaultValue={source}
                                    onValueChange={setSource}
                                />
                                {source === 'provider_import' && (
                                    <InputField
                                        name="provider_event_id"
                                        label={copy.provider_event_id}
                                        defaultValue={
                                            row.meta?.providerEventId ?? ''
                                        }
                                        error={errors.provider_event_id}
                                    />
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor={`notes-${row.id}`}>
                                        {isAmendment
                                            ? copy.amendment_rationale
                                            : copy.attendance_note}
                                    </Label>
                                    <Textarea
                                        id={`notes-${row.id}`}
                                        name="notes"
                                        defaultValue={row.meta?.notes ?? ''}
                                        required={isAmendment}
                                        aria-invalid={Boolean(errors.notes)}
                                        aria-describedby={
                                            errors.notes
                                                ? `notes-${row.id}-error`
                                                : undefined
                                        }
                                    />
                                    {errors.notes && (
                                        <p
                                            id={`notes-${row.id}-error`}
                                            role="alert"
                                            className="text-xs text-destructive"
                                        >
                                            {errors.notes}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {isAmendment
                                        ? copy.save_attributed_amendment
                                        : copy.record_attendance}
                                </Button>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}

function InputField({
    name,
    label,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
}) {
    const errorId = `${name}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={defaultValue}
                required
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
            />
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function attendanceStatusOptions(copy: Record<string, string>) {
    return ['not_recorded', 'present', 'partial', 'absent'].map((id) => ({
        id,
        name: copy[`value_${id}`] ?? humanize(id),
    }));
}
