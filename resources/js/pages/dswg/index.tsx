import { Form, Head, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    Download,
    Gavel,
    MessageSquare,
    UsersRound,
} from 'lucide-react';
import {
    approveMinutes,
    recordOutcomes,
    respondInvitation,
    storeAction,
    storeCollaborationMessage,
    storeCollaborationThread,
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
import SearchableSelect from '@/components/searchable-select';
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
import { interpolate } from '@/hooks/use-localization';
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
type CollaborationThread = {
    id: string;
    title: string;
    topic: string;
    status: string;
    workingGroup: string;
    creator: string;
    lastActivityAt: string;
    messageCount: number;
    messages: Array<{
        id: string;
        author: string;
        body: string;
        checksum: string;
        postedAt: string;
    }>;
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
    threads: CollaborationThread[];
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

function useDswgCopy(): Record<string, string> {
    return usePage().props.localization.dswg;
}

export default function DswgIndex({
    workspace,
    filters,
    capabilities,
    meetings,
    series,
    threads,
    catalogue,
    options,
}: Props) {
    const page = usePage();
    const currentUserId = page.props.auth.user.id;
    const copy = page.props.localization.dswg;

    return (
        <>
            <Head title={copy.head_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.eyebrow}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">{copy.title}</h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        {copy.description}
                    </p>
                </section>
                {capabilities.manage && <DswgCoordinationForms {...options} />}
                <Card>
                    <CardHeader>
                        <CardTitle>{copy.series_title}</CardTitle>
                        <CardDescription>
                            {copy.series_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {series.length === 0 ? (
                            <WorkspaceEmptyState
                                title={copy.no_series}
                                description={copy.no_series_description}
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
                                                {copy[
                                                    `value_${meetingSeries.status}`
                                                ] ?? meetingSeries.status}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            {meetingSeries.title}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-2 text-sm text-muted-foreground">
                                        <p>{meetingSeries.workingGroup}</p>
                                        <p>
                                            {copy.every}{' '}
                                            {meetingSeries.interval}{' '}
                                            {meetingSeries.frequency}{' '}
                                            {copy.periods} {copy.separator}{' '}
                                            {meetingSeries.timezone}
                                        </p>
                                        <p>
                                            {meetingSeries.generatedMeetings}{' '}
                                            {copy.occurrences_generated}
                                        </p>
                                        <p>
                                            {copy.next_occurrence}{' '}
                                            {new Date(
                                                meetingSeries.nextOccurrenceAt,
                                            ).toLocaleString()}
                                        </p>
                                        <p>
                                            {copy.ends_on}{' '}
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
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <MessageSquare aria-hidden="true" />
                                {copy.collaboration_threads}
                            </CardTitle>
                            <CardDescription>
                                {copy.collaboration_description}
                            </CardDescription>
                        </div>
                        {capabilities.participate && (
                            <CreateThreadSheet
                                workingGroups={options.workingGroups}
                                copy={copy}
                            />
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        {threads.length === 0 ? (
                            <WorkspaceEmptyState
                                title={copy.no_collaboration_threads}
                                description={copy.no_collaboration_description}
                                className="min-h-44 border lg:col-span-2"
                            />
                        ) : (
                            threads.map((thread) => (
                                <CollaborationThreadCard
                                    key={thread.id}
                                    thread={thread}
                                    canReply={capabilities.participate}
                                    copy={copy}
                                />
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
                                <CardTitle>{copy.meeting_workspace}</CardTitle>
                                <CardDescription>
                                    {copy.meeting_workspace_description}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {meetings.length === 0 && (
                            <WorkspaceEmptyState
                                title={copy.no_meetings}
                                description={copy.no_meetings_description}
                                className="min-h-52 border"
                            />
                        )}
                        {meetings.map((meeting) => (
                            <MeetingCard
                                key={meeting.id}
                                meeting={meeting}
                                currentUserId={currentUserId}
                                capabilities={capabilities}
                                options={options}
                                catalogue={catalogue}
                                copy={copy}
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
                                            { workspace: 'dswg', format },
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
                                bulkExport={{ workspace: 'dswg', filters }}
                                renderActions={(row) => (
                                    <ActionControls
                                        row={row}
                                        currentUserId={currentUserId}
                                        capabilities={capabilities}
                                    />
                                )}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_actions}
                                description={copy.no_actions_description}
                                className="min-h-72 border-0"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function CreateThreadSheet({
    workingGroups,
    copy,
}: {
    workingGroups: DswgOption[];
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.create_thread}
            triggerLabel={copy.new_thread}
            description={copy.create_thread_description}
            icon={MessageSquare}
        >
            <Form
                action={storeCollaborationThread()}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="collaboration-working-group"
                            name="dswg_working_group_id"
                            label={copy.working_group}
                            options={workingGroups}
                            error={errors.dswg_working_group_id}
                        />
                        <Field label={copy.thread_title} error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <Field
                            label={copy.opening_contribution}
                            error={errors.topic}
                        >
                            <textarea
                                name="topic"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <Button type="submit" disabled={processing}>
                            {copy.create_thread}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function CollaborationThreadCard({
    thread,
    canReply,
    copy,
}: {
    thread: CollaborationThread;
    canReply: boolean;
    copy: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <CardTitle className="text-base">{thread.title}</CardTitle>
                    <Badge variant="outline">{thread.status}</Badge>
                </div>
                <CardDescription>
                    {thread.workingGroup} {copy.separator} {thread.creator}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div
                    className="max-h-72 space-y-3 overflow-y-auto"
                    aria-label={copy.thread_messages}
                >
                    {thread.messages.map((message) => (
                        <article
                            key={message.id}
                            className="rounded-lg border p-3 text-sm"
                        >
                            <div className="flex justify-between gap-3 font-medium">
                                <span>{message.author}</span>
                                <time
                                    dateTime={message.postedAt}
                                    className="text-xs text-muted-foreground"
                                >
                                    {new Date(
                                        message.postedAt,
                                    ).toLocaleString()}
                                </time>
                            </div>
                            <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                                {message.body}
                            </p>
                            <p className="mt-2 font-mono text-[10px] text-muted-foreground">
                                {copy.checksum} {message.checksum.slice(0, 16)}
                            </p>
                        </article>
                    ))}
                </div>
                {canReply && thread.status === 'open' && (
                    <FormSheet
                        title={copy.post_contribution}
                        triggerLabel={copy.reply}
                        description={thread.title}
                        icon={MessageSquare}
                    >
                        <Form
                            action={storeCollaborationMessage({
                                thread: thread.id,
                            })}
                            className="grid gap-4"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field
                                        label={copy.contribution}
                                        error={errors.body}
                                    >
                                        <textarea
                                            name="body"
                                            required
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        {copy.post_contribution}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            </CardContent>
        </Card>
    );
}

function MeetingCard({
    meeting,
    currentUserId,
    capabilities,
    options,
    catalogue,
    copy,
}: {
    meeting: Meeting;
    currentUserId: string;
    capabilities: Props['capabilities'];
    options: Props['options'];
    catalogue: Props['catalogue'];
    copy: Record<string, string>;
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
                        {meeting.reference} {copy.separator} {meeting.title}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {meeting.workingGroup} {copy.separator}{' '}
                        {new Date(meeting.startsAt).toLocaleString()}{' '}
                        {copy.separator} {copy.quorum} {meeting.quorumRequired}
                        {'/'}
                        {meeting.invitees.length}
                    </p>
                </div>
                <div className="text-sm text-muted-foreground">
                    {meeting.decisions} {copy.decisions} {copy.separator}{' '}
                    {meeting.actions} {copy.actions}
                </div>
            </div>
            <DswgDocumentControls
                subjectId={meeting.id}
                subjectType="meeting"
                documents={meeting.documents}
                canUpload={capabilities.manage && meeting.status !== 'closed'}
                meetingStatus={meeting.status}
            />
            <p className="text-sm">
                <span className="font-medium">{copy.agenda}</span>{' '}
                {meeting.agenda}
            </p>
            {capabilities.participate &&
                meeting.invitationStatus &&
                meeting.status === 'scheduled' && (
                    <Form
                        action={respondInvitation({ meeting: meeting.id })}
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
                                            {copy[`value_${status}`] ?? status}
                                        </Button>
                                    ),
                                )}
                            </>
                        )}
                    </Form>
                )}
            {capabilities.manage && meeting.status === 'scheduled' && (
                <FormSheet
                    title={copy.record_outcomes_title}
                    triggerLabel={copy.record_outcomes}
                    description={interpolate(copy.record_outcomes_for_meeting, {
                        reference: meeting.reference,
                    })}
                >
                    <Form
                        action={recordOutcomes({ meeting: meeting.id })}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <Field
                                    label={copy.draft_minutes}
                                    error={errors.minutes}
                                >
                                    <textarea
                                        name="minutes"
                                        required
                                        className={textareaClass}
                                    />
                                </Field>
                                <Field
                                    label={copy.members_present}
                                    error={errors.present_user_ids}
                                >
                                    <Multi
                                        name="present_user_ids[]"
                                        options={meeting.invitees}
                                    />
                                </Field>
                                <Button type="submit" disabled={processing}>
                                    {copy.record_outcomes_submit}
                                </Button>
                            </>
                        )}
                    </Form>
                </FormSheet>
            )}
            {meeting.minutes && (
                <div className="rounded-lg border bg-muted/20 p-4 text-sm">
                    <p className="font-medium">{copy.minutes}</p>
                    <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                        {meeting.minutes}
                    </p>
                </div>
            )}
            {capabilities.manage &&
                meeting.status === 'minutes_pending' &&
                meeting.minutesRecordedBy !== currentUserId && (
                    <FormSheet
                        title={copy.approve_minutes_title}
                        triggerLabel={copy.approve_minutes}
                        description={interpolate(
                            copy.approve_minutes_for_meeting,
                            { reference: meeting.reference },
                        )}
                    >
                        <Form
                            action={approveMinutes({ meeting: meeting.id })}
                            className="grid gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        {hasCleanMinutes
                                            ? copy.clean_minutes_ready
                                            : copy.clean_minutes_required}
                                    </p>
                                    <Input
                                        name="approval_comment"
                                        required
                                        placeholder={copy.minutes_comment}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || !hasCleanMinutes
                                        }
                                    >
                                        {copy.approve_minutes}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            {capabilities.manage &&
                ['minutes_pending', 'closed'].includes(meeting.status) && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <DecisionForm meeting={meeting} copy={copy} />
                        <ActionForm
                            meeting={meeting}
                            options={options}
                            catalogue={catalogue}
                            copy={copy}
                        />
                    </div>
                )}
        </div>
    );
}

function DecisionForm({
    meeting,
    copy,
}: {
    meeting: Meeting;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.register_decision_title}
            triggerLabel={copy.register_decision}
            icon={Gavel}
            description={interpolate(copy.register_decision_for_meeting, {
                reference: meeting.reference,
            })}
        >
            <Form
                action={storeDecision({ meeting: meeting.id })}
                className="grid gap-3"
                resetOnSuccess
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="code"
                            required
                            placeholder={copy.decision_code_placeholder}
                        />
                        <Input
                            name="title"
                            required
                            placeholder={copy.decision_title}
                        />
                        <textarea
                            name="decision_text"
                            required
                            className={textareaClass}
                            placeholder={copy.adopted_decision}
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
                            label={copy.decision_datetime}
                            required
                            includeTime
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.register_decision_title}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ActionForm({
    meeting,
    options,
    catalogue,
    copy,
}: {
    meeting: Meeting;
    options: Props['options'];
    catalogue: Props['catalogue'];
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.assign_action_title}
            triggerLabel={copy.assign_action}
            icon={UsersRound}
            description={interpolate(copy.assign_action_for_meeting, {
                reference: meeting.reference,
            })}
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `${copy.using_catalogue} ${catalogue.version}`
                    : copy.no_effective_catalogue
            }
        >
            <Form
                action={storeAction({ meeting: meeting.id })}
                className="grid gap-3"
                resetOnSuccess
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="code"
                            required
                            placeholder={copy.action_code_placeholder}
                        />
                        <Input
                            name="title"
                            required
                            placeholder={copy.action_title}
                        />
                        <textarea
                            name="description"
                            required
                            className={textareaClass}
                            placeholder={copy.deliverable_evidence}
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
                            label={copy.due_date}
                            required
                        />
                        <StaticSearchableSelect
                            id={`action-priority-${meeting.id}`}
                            name="priority"
                            values={['medium', 'high', 'critical', 'low']}
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.assign_action}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ActionControls({
    row,
    currentUserId,
    capabilities,
}: {
    row: WorkspaceRow;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const copy = useDswgCopy();
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
                subjectId={row.id}
                subjectType="action"
                documents={documents}
                canUpload={row.status === 'in_progress' && canManage}
            />
            {row.status === 'open' && canManage && (
                <TransitionSheet
                    actionId={row.id}
                    transition="start"
                    label={copy.start}
                />
            )}
            {row.status === 'in_progress' && canManage && (
                <FormSheet
                    title={copy.submit_completion_title}
                    triggerLabel={copy.submit_completion}
                    description={copy.submit_completion_description}
                >
                    <Form
                        action={transitionAction({ action: row.id })}
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
                                        ? copy.clean_evidence_ready
                                        : copy.clean_evidence_required}
                                </p>
                                <Input
                                    name="completion_evidence"
                                    placeholder={copy.evidence_narrative}
                                />
                                <Input
                                    name="comment"
                                    required
                                    placeholder={copy.completion_comment}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing || !hasCleanEvidence}
                                >
                                    {copy.submit_completion}
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
                            transition="verify"
                            label={copy.verify}
                        />
                        <TransitionSheet
                            actionId={row.id}
                            transition="reject"
                            label={copy.return}
                            variant="outline"
                        />
                    </>
                )}
        </div>
    );
}

function TransitionSheet({
    actionId,
    transition,
    label,
    variant = 'default',
}: {
    actionId: string;
    transition: string;
    label: string;
    variant?: 'default' | 'outline';
}) {
    const copy = useDswgCopy();

    return (
        <FormSheet
            title={interpolate(copy.action_transition_title, { label })}
            triggerLabel={label}
            description={interpolate(copy.action_transition_description, {
                label,
            })}
        >
            <Form
                action={transitionAction({ action: actionId })}
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
                            defaultValue={`${label} ${copy.transition_comment}`}
                            aria-label={interpolate(
                                copy.action_rationale_label,
                                { label },
                            )}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant={variant}
                            disabled={processing}
                        >
                            {copy.confirm} {label.toLowerCase()}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
