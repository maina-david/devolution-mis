import { Form, Head, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    Download,
    Gavel,
    UsersRound,
} from 'lucide-react';
import {
    approveMinutes,
    recordOutcomes,
    respondInvitation,
    storeAction,
    storeDecision,
    transitionAction,
} from '@/actions/App/Http/Controllers/DswgCoordinationController';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import DswgCoordinationForms, {
    Field,
    Multi,
    Select,
    textareaClass,
} from '@/components/dswg-coordination-forms';
import type { DswgOption } from '@/components/dswg-coordination-forms';
import DswgDocumentControls from '@/components/dswg-document-controls';
import FormSheet from '@/components/form-sheet';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspaceDocument,
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { exportMethod } from '@/routes/workspace';

type Meeting = {
    id: string;
    reference: string;
    title: string;
    workingGroup: string;
    startsAt: string;
    endsAt: string;
    mode: string;
    status: string;
    agenda: string;
    minutes: string | null;
    quorumRequired: number;
    invitees: DswgOption[];
    decisions: number;
    actions: number;
    decisionOptions: DswgOption[];
    invitationStatus: string | null;
    minutesRecordedBy: string | null;
    documents: WorkspaceDocument[];
};
type MeetingSeries = {
    id: string;
    referencePrefix: string;
    title: string;
    workingGroup: string;
    frequency: string;
    interval: number;
    timezone: string;
    nextOccurrenceAt: string;
    endsOn: string;
    status: string;
    generatedMeetings: number;
};
type Props = {
    workspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    filters: { from?: string; to?: string; search?: string };
    capabilities: {
        manage: boolean;
        participate: boolean;
        manageActions: boolean;
        verifyActions: boolean;
    };
    meetings: Meeting[];
    series: MeetingSeries[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: {
        workingGroups: DswgOption[];
        meetings: DswgOption[];
        counties: DswgOption[];
        sectors: DswgOption[];
        organizations: DswgOption[];
        users: DswgOption[];
    };
};

export default function DswgIndex({
    workspace,
    filters,
    capabilities,
    meetings,
    series,
    catalogue,
    options,
}: Props) {
    const page = usePage();
    const team = page.props.currentTeam;
    const currentUserId = page.props.auth.user.id;

    if (!team) {
        return null;
    }

    return (
        <>
            <Head title="DSWG coordination" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        Sector coordination and accountability
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">
                        Devolution Sector Working Groups
                    </h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        Coordinate membership, invitations, quorum, agendas,
                        approved minutes, decisions, accountable actions,
                        deadlines, reminders, and independent closure.
                    </p>
                </section>
                {capabilities.manage && (
                    <DswgCoordinationForms teamSlug={team.slug} {...options} />
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Recurring meeting series</CardTitle>
                        <CardDescription>
                            Rolling, idempotent schedules generated in the
                            selected IANA timezone with a governed workflow for
                            every occurrence.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {series.length === 0 ? (
                            <WorkspaceEmptyState
                                title="No recurring series"
                                description="Create a series to maintain future meeting occurrences automatically."
                                className="min-h-40 border md:col-span-2 xl:col-span-3"
                            />
                        ) : (
                            series.map((meetingSeries) => (
                                <Card key={meetingSeries.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <CardTitle className="text-base">
                                                {meetingSeries.referencePrefix}
                                            </CardTitle>
                                            <Badge variant="secondary">
                                                {meetingSeries.status}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            {meetingSeries.title}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-2 text-sm text-muted-foreground">
                                        <p>{meetingSeries.workingGroup}</p>
                                        <p>
                                            Every {meetingSeries.interval}{' '}
                                            {meetingSeries.frequency} period(s)
                                            · {meetingSeries.timezone}
                                        </p>
                                        <p>
                                            {meetingSeries.generatedMeetings}{' '}
                                            occurrence(s) generated
                                        </p>
                                        <p>
                                            Next:{' '}
                                            {new Date(
                                                meetingSeries.nextOccurrenceAt,
                                            ).toLocaleString()}
                                        </p>
                                        <p>
                                            Ends:{' '}
                                            {new Date(
                                                meetingSeries.endsOn,
                                            ).toLocaleDateString()}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </CardContent>
                </Card>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                />
                <Card>
                    <CardHeader>
                        <div className="flex gap-3">
                            <CalendarDays
                                className="text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <CardTitle>Meeting workspace</CardTitle>
                                <CardDescription>
                                    Tracked invitations, attendance, quorum,
                                    minutes, decisions, and actions.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {meetings.length === 0 && (
                            <WorkspaceEmptyState
                                title="No meetings available"
                                description="Adjust the reporting dates or schedule the first sector working group meeting."
                                className="min-h-52 border"
                            />
                        )}
                        {meetings.map((meeting) => (
                            <MeetingCard
                                key={meeting.id}
                                meeting={meeting}
                                teamSlug={team.slug}
                                currentUserId={currentUserId}
                                capabilities={capabilities}
                                options={options}
                                catalogue={catalogue}
                            />
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex gap-3">
                            <ClipboardList
                                className="text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <CardTitle>{workspace.title}</CardTitle>
                                <CardDescription>
                                    {workspace.description}
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['csv', 'xlsx', 'pdf', 'json'].map((format) => (
                                <Button
                                    key={format}
                                    asChild
                                    size="sm"
                                    variant="outline"
                                >
                                    <a
                                        href={exportMethod.url(
                                            {
                                                current_team: team.slug,
                                                workspace: 'dswg',
                                                format,
                                            },
                                            { query: filters },
                                        )}
                                    >
                                        <Download aria-hidden="true" />
                                        {format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {workspace.rows.length ? (
                            <WorkspaceDataTable
                                columns={workspace.columns}
                                rows={workspace.rows}
                                pagination={workspace.pagination}
                                bulkExport={{
                                    teamSlug: team.slug,
                                    workspace: 'dswg',
                                    filters,
                                }}
                                renderActions={(row) => (
                                    <ActionControls
                                        row={row}
                                        teamSlug={team.slug}
                                        currentUserId={currentUserId}
                                        capabilities={capabilities}
                                    />
                                )}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No matching accountable actions"
                                description="Adjust the filters or record actions from an approved sector working group meeting."
                                className="min-h-72 border-0"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function MeetingCard({
    meeting,
    teamSlug,
    currentUserId,
    capabilities,
    options,
    catalogue,
}: {
    meeting: Meeting;
    teamSlug: string;
    currentUserId: string;
    capabilities: Props['capabilities'];
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    const hasCleanMinutes = meeting.documents.some(
        (document) =>
            document.purpose === 'dswg-minutes-record' &&
            document.scanStatus === 'clean',
    );

    return (
        <div className="grid gap-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {meeting.status.replaceAll('_', ' ')}
                        </Badge>
                        <Badge variant="secondary">{meeting.mode}</Badge>
                    </div>
                    <h3 className="mt-2 font-bold">
                        {meeting.reference} · {meeting.title}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {meeting.workingGroup} ·{' '}
                        {new Date(meeting.startsAt).toLocaleString()} · quorum{' '}
                        {meeting.quorumRequired}/{meeting.invitees.length}
                    </p>
                </div>
                <div className="text-sm text-muted-foreground">
                    {meeting.decisions} decisions · {meeting.actions} actions
                </div>
            </div>
            <DswgDocumentControls
                teamSlug={teamSlug}
                subjectId={meeting.id}
                subjectType="meeting"
                documents={meeting.documents}
                canUpload={capabilities.manage && meeting.status !== 'closed'}
                meetingStatus={meeting.status}
            />
            <p className="text-sm">
                <span className="font-medium">Agenda:</span> {meeting.agenda}
            </p>
            {capabilities.participate &&
                meeting.invitationStatus &&
                meeting.status === 'scheduled' && (
                    <Form
                        action={respondInvitation({
                            current_team: teamSlug,
                            meeting: meeting.id,
                        })}
                        className="flex flex-wrap gap-2"
                    >
                        {({ processing }) => (
                            <>
                                {['accepted', 'tentative', 'declined'].map(
                                    (status) => (
                                        <Button
                                            key={status}
                                            type="submit"
                                            name="invitation_status"
                                            value={status}
                                            size="sm"
                                            variant={
                                                meeting.invitationStatus ===
                                                status
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            disabled={processing}
                                        >
                                            {status}
                                        </Button>
                                    ),
                                )}
                            </>
                        )}
                    </Form>
                )}
            {capabilities.manage && meeting.status === 'scheduled' && (
                <FormSheet
                    title="Record meeting outcomes"
                    triggerLabel="Record outcomes"
                    description={`Record attendance, quorum and draft minutes for ${meeting.reference}.`}
                >
                    <Form
                        action={recordOutcomes({
                            current_team: teamSlug,
                            meeting: meeting.id,
                        })}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field
                                    label="Draft minutes"
                                    error={errors.minutes}
                                >
                                    <textarea
                                        name="minutes"
                                        required
                                        className={textareaClass}
                                    />
                                </Field>
                                <Field
                                    label="Members present"
                                    error={errors.present_user_ids}
                                >
                                    <Multi
                                        name="present_user_ids[]"
                                        options={meeting.invitees}
                                    />
                                </Field>
                                <Button type="submit" disabled={processing}>
                                    Record outcomes and quorum
                                </Button>
                            </>
                        )}
                    </Form>
                </FormSheet>
            )}
            {meeting.minutes && (
                <div className="rounded-lg border bg-muted/20 p-4 text-sm">
                    <p className="font-medium">Minutes</p>
                    <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                        {meeting.minutes}
                    </p>
                </div>
            )}
            {capabilities.manage &&
                meeting.status === 'minutes_pending' &&
                meeting.minutesRecordedBy !== currentUserId && (
                    <FormSheet
                        title="Approve meeting minutes"
                        triggerLabel="Approve minutes"
                        description={`Independently review and approve the minutes for ${meeting.reference}.`}
                    >
                        <Form
                            action={approveMinutes({
                                current_team: teamSlug,
                                meeting: meeting.id,
                            })}
                            className="grid gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        {hasCleanMinutes
                                            ? 'A clean repository-linked minutes record is ready for approval.'
                                            : 'Upload a clean minutes record before approval can be completed.'}
                                    </p>
                                    <Input
                                        name="approval_comment"
                                        required
                                        placeholder="Independent minutes approval comment"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || !hasCleanMinutes
                                        }
                                    >
                                        Approve minutes
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            {capabilities.manage &&
                ['minutes_pending', 'closed'].includes(meeting.status) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <DecisionForm meeting={meeting} teamSlug={teamSlug} />
                        <ActionForm
                            meeting={meeting}
                            teamSlug={teamSlug}
                            options={options}
                            catalogue={catalogue}
                        />
                    </div>
                )}
        </div>
    );
}

function DecisionForm({
    meeting,
    teamSlug,
}: {
    meeting: Meeting;
    teamSlug: string;
}) {
    return (
        <FormSheet
            title="Register adopted decision"
            triggerLabel="Register decision"
            icon={Gavel}
            description={`Record an adopted decision from ${meeting.reference}.`}
        >
            <Form
                action={storeDecision({
                    current_team: teamSlug,
                    meeting: meeting.id,
                })}
                className="grid gap-3"
                resetOnSuccess
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="code"
                            required
                            placeholder="DSWG-DEC-001"
                        />
                        <Input
                            name="title"
                            required
                            placeholder="Decision title"
                        />
                        <textarea
                            name="decision_text"
                            required
                            className={textareaClass}
                            placeholder="Adopted decision"
                        />
                        <StaticSearchableSelect
                            id={`decision-type-${meeting.id}`}
                            name="decision_type"
                            values={[
                                'resolution',
                                'recommendation',
                                'endorsement',
                                'deferral',
                            ]}
                        />
                        <DatePickerField
                            name="decided_at"
                            label="Decision date and time"
                            required
                            includeTime
                        />
                        <Button type="submit" disabled={processing}>
                            Register adopted decision
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ActionForm({
    meeting,
    teamSlug,
    options,
    catalogue,
}: {
    meeting: Meeting;
    teamSlug: string;
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Assign accountable action"
            triggerLabel="Assign action"
            icon={UsersRound}
            description={`Create a deadline-bound action from ${meeting.reference}.`}
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'No checksum-valid published reference catalogue is currently effective.'
            }
        >
            <Form
                action={storeAction({
                    current_team: teamSlug,
                    meeting: meeting.id,
                })}
                className="grid gap-3"
                resetOnSuccess
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="code"
                            required
                            placeholder="DSWG-ACT-001"
                        />
                        <Input
                            name="title"
                            required
                            placeholder="Action title"
                        />
                        <textarea
                            name="description"
                            required
                            className={textareaClass}
                            placeholder="Deliverable and acceptance evidence"
                        />
                        <Select
                            name="dswg_decision_id"
                            options={meeting.decisionOptions}
                            optional
                        />
                        <Select
                            name="accountable_user_id"
                            options={options.users}
                        />
                        <Select
                            name="accountable_organization_id"
                            options={options.organizations}
                            optional
                        />
                        <Select
                            name="county_id"
                            options={options.counties}
                            optional
                        />
                        <DatePickerField
                            name="due_on"
                            label="Due date"
                            required
                        />
                        <StaticSearchableSelect
                            id={`action-priority-${meeting.id}`}
                            name="priority"
                            values={['medium', 'high', 'critical', 'low']}
                        />
                        <Button type="submit" disabled={processing}>
                            Assign action
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ActionControls({
    row,
    teamSlug,
    currentUserId,
    capabilities,
}: {
    row: WorkspaceRow;
    teamSlug: string;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const canManage =
        capabilities.manage ||
        (capabilities.manageActions &&
            row.meta?.accountableUserId === currentUserId);
    const documents = row.documents ?? [];
    const hasCleanEvidence = documents.some(
        (document) =>
            document.purpose === 'dswg-action-evidence' &&
            document.scanStatus === 'clean',
    );

    return (
        <div className="flex flex-wrap gap-2">
            <DswgDocumentControls
                teamSlug={teamSlug}
                subjectId={row.id}
                subjectType="action"
                documents={documents}
                canUpload={row.status === 'in_progress' && canManage}
            />
            {row.status === 'open' && canManage && (
                <TransitionSheet
                    actionId={row.id}
                    teamSlug={teamSlug}
                    transition="start"
                    label="Start"
                />
            )}
            {row.status === 'in_progress' && canManage && (
                <FormSheet
                    title="Submit action completion"
                    triggerLabel="Submit completion"
                    description="Submit repository-backed completion evidence for independent verification."
                >
                    <Form
                        action={transitionAction({
                            current_team: teamSlug,
                            action: row.id,
                        })}
                        className="grid gap-4"
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="transition"
                                    value="submit_completion"
                                />
                                <input
                                    type="hidden"
                                    name="progress_percentage"
                                    value="100"
                                />
                                <p className="text-sm text-muted-foreground">
                                    {hasCleanEvidence
                                        ? 'A clean action-evidence record is linked and ready for submission.'
                                        : 'Upload a clean action-evidence record before submitting completion.'}
                                </p>
                                <Input
                                    name="completion_evidence"
                                    placeholder="Optional evidence narrative or repository reference"
                                />
                                <Input
                                    name="comment"
                                    required
                                    placeholder="Completion submission comment"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing || !hasCleanEvidence}
                                >
                                    Submit completion
                                </Button>
                            </>
                        )}
                    </Form>
                </FormSheet>
            )}
            {row.status === 'completion_review' &&
                capabilities.verifyActions && (
                    <>
                        <TransitionSheet
                            actionId={row.id}
                            teamSlug={teamSlug}
                            transition="verify"
                            label="Verify"
                        />
                        <TransitionSheet
                            actionId={row.id}
                            teamSlug={teamSlug}
                            transition="reject"
                            label="Return"
                            variant="outline"
                        />
                    </>
                )}
        </div>
    );
}

function TransitionSheet({
    actionId,
    teamSlug,
    transition,
    label,
    variant = 'default',
}: {
    actionId: string;
    teamSlug: string;
    transition: string;
    label: string;
    variant?: 'default' | 'outline';
}) {
    return (
        <FormSheet
            title={`${label} accountable action`}
            triggerLabel={label}
            description={`${label} this action through the governed DSWG workflow.`}
        >
            <Form
                action={transitionAction({
                    current_team: teamSlug,
                    action: actionId,
                })}
                className="grid gap-4"
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="transition"
                            value={transition}
                        />
                        <Input
                            name="comment"
                            required
                            defaultValue={`${label} action through the governed DSWG workflow.`}
                            aria-label={`${label} rationale`}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant={variant}
                            disabled={processing}
                        >
                            Confirm {label.toLowerCase()}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
