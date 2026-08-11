import { Form, Head, usePage } from '@inertiajs/react';
import {
    ClockAlert,
    Download,
    Eye,
    FileUp,
    Headphones,
    MoreHorizontal,
    Plus,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import { storeSupportTicket } from '@/actions/App/Http/Controllers/LinkedDocumentController';
import {
    assign,
    store,
    transition,
} from '@/actions/App/Http/Controllers/SupportDeskController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
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
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
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
import { download, preview } from '@/routes/evidence';

type Activity = {
    id: string;
    actor: string;
    type: string;
    fromStatus: string;
    toStatus: string;
    narrative: string;
    occurredAt: string;
    checksum: string;
};

type DocumentRecord = {
    id: string;
    title: string;
    originalName: string | null;
    mimeType: string | null;
    sizeBytes: number;
    checksum: string;
    scanStatus: string;
    ocrStatus: string;
    recordStatus: string;
};

type TicketDetail = {
    id: string;
    reference: string;
    subject: string;
    description: string;
    category: string;
    priority: string;
    channel: string;
    status: string;
    county: CountyIdentityValue | null;
    requester: { id: string; name: string; email: string };
    assignee: { id: string; name: string; email: string } | null;
    resolver: string | null;
    closer: string | null;
    resolutionSummary: string | null;
    requestedAt: string;
    firstResponseDueAt: string;
    firstRespondedAt: string | null;
    resolutionDueAt: string;
    resolvedAt: string | null;
    closedAt: string | null;
    referenceData: {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    activities: Activity[];
    documents: DocumentRecord[];
};

type Props = {
    workspace: {
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    details: Record<string, TicketDetail>;
    filters: Record<string, string | undefined>;
    summary: {
        total: number;
        active: number;
        unassigned: number;
        overdue: number;
    };
    options: {
        counties: CountyIdentityValue[];
        assignees: SearchableSelectOption[];
    };
    catalogue:
        | { available: false }
        | { available: true; version: number; checksum: string };
    capabilities: {
        submit: boolean;
        manage: boolean;
        resolve: boolean;
        national: boolean;
        userId: string;
    };
};

const statusOptions = [
    'open',
    'triaged',
    'in_progress',
    'awaiting_requester',
    'resolved',
    'closed',
].map((status) => ({ id: status, name: humanize(status) }));

export default function SupportDesk({
    workspace,
    details,
    filters,
    summary,
    options,
    catalogue,
    capabilities,
}: Props) {
    const { currentTeam } = usePage().props;
    const [selectedTicketId, setSelectedTicketId] = useState<string | null>(
        null,
    );

    if (!currentTeam) {
        return null;
    }

    const selectedTicket = selectedTicketId
        ? details[selectedTicketId]
        : undefined;

    return (
        <>
            <Head title="Service desk" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                Operational support and SLA assurance
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                IDMIS service desk
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                Submit, triage, investigate and independently
                                accept support requests with county scope,
                                immutable activity history, governed records and
                                monitored response targets.
                            </p>
                        </div>
                        {capabilities.submit && (
                            <CreateTicketSheet
                                teamSlug={currentTeam.slug}
                                counties={options.counties}
                                national={capabilities.national}
                                catalogueAvailable={catalogue.available}
                            />
                        )}
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        title="All authorized tickets"
                        value={summary.total}
                        description="Within your county or national scope"
                        icon={Headphones}
                    />
                    <Metric
                        title="Active workload"
                        value={summary.active}
                        description="Not yet resolved or accepted"
                        icon={ClockAlert}
                    />
                    <Metric
                        title="Awaiting triage"
                        value={summary.unassigned}
                        description="Open requests without an owner"
                        icon={UserRoundCheck}
                    />
                    <Metric
                        title="SLA overdue"
                        value={summary.overdue}
                        description="Resolution target has passed"
                        icon={ShieldCheck}
                    />
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    cycles={[]}
                    selectFilters={[
                        ...(capabilities.national
                            ? [
                                  {
                                      key: 'county_id',
                                      label: 'County',
                                      options: options.counties,
                                      value: filters.county_id,
                                  },
                              ]
                            : []),
                        {
                            key: 'status',
                            label: 'Ticket status',
                            options: statusOptions,
                            value: filters.status,
                        },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Support case register</CardTitle>
                        <CardDescription>
                            Server-paginated operational records. Select rows
                            for authorized CSV, XLSX, JSON or PDF export.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <WorkspaceDataTable
                            columns={workspace.columns}
                            rows={workspace.rows}
                            pagination={workspace.pagination}
                            bulkExport={{
                                teamSlug: currentTeam.slug,
                                workspace: 'support-desk',
                                filters,
                            }}
                            allowFilteredBulkSelection
                            renderActionControl={(row) => (
                                <TicketMenu
                                    detail={details[row.id]}
                                    onView={() => setSelectedTicketId(row.id)}
                                />
                            )}
                        />
                    </CardContent>
                </Card>
            </main>

            <TicketSheet
                teamSlug={currentTeam.slug}
                ticket={selectedTicket}
                open={Boolean(selectedTicket)}
                onOpenChange={(open) => !open && setSelectedTicketId(null)}
                assignees={options.assignees}
                capabilities={capabilities}
            />
        </>
    );
}

function CreateTicketSheet({
    teamSlug,
    counties,
    national,
    catalogueAvailable,
}: {
    teamSlug: string;
    counties: CountyIdentityValue[];
    national: boolean;
    catalogueAvailable: boolean;
}) {
    return (
        <FormSheet
            title="Submit support request"
            description="Create a governed service request. Personally sensitive narrative is encrypted at rest."
            triggerLabel="New support ticket"
            icon={Plus}
            triggerDisabled={!catalogueAvailable}
            triggerTitle={
                catalogueAvailable
                    ? undefined
                    : 'No effective governed reference catalogue is available.'
            }
        >
            <Form {...store.form(teamSlug)} resetOnSuccess>
                {({ errors, processing }) => (
                    <FieldGroup>
                        {national && (
                            <SearchableSelect
                                id="support-county"
                                name="county_id"
                                label="County"
                                options={counties}
                                optional
                                error={errors.county_id}
                            />
                        )}
                        {!national && counties.length === 1 && (
                            <input
                                type="hidden"
                                name="county_id"
                                value={counties[0].id}
                            />
                        )}
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SearchableSelect
                                id="support-category"
                                name="category"
                                label="Category"
                                options={[
                                    {
                                        id: 'access',
                                        name: 'Access and identity',
                                    },
                                    {
                                        id: 'service_request',
                                        name: 'Service request',
                                    },
                                    {
                                        id: 'data_quality',
                                        name: 'Data quality',
                                    },
                                    { id: 'integration', name: 'Integration' },
                                    {
                                        id: 'training',
                                        name: 'Training and adoption',
                                    },
                                    {
                                        id: 'document',
                                        name: 'Documents and OCR',
                                    },
                                    { id: 'other', name: 'Other' },
                                ]}
                                error={errors.category}
                            />
                            <SearchableSelect
                                id="support-priority"
                                name="priority"
                                label="Priority"
                                options={[
                                    { id: 'low', name: 'Low' },
                                    { id: 'medium', name: 'Medium' },
                                    { id: 'high', name: 'High' },
                                    { id: 'critical', name: 'Critical' },
                                ]}
                                defaultValue="medium"
                                error={errors.priority}
                            />
                        </div>
                        <input type="hidden" name="channel" value="web" />
                        <Field data-invalid={Boolean(errors.subject)}>
                            <FieldLabel htmlFor="support-subject">
                                Subject
                            </FieldLabel>
                            <Input
                                id="support-subject"
                                name="subject"
                                required
                                maxLength={255}
                                aria-invalid={Boolean(errors.subject)}
                                aria-describedby={
                                    errors.subject
                                        ? 'support-subject-error'
                                        : undefined
                                }
                            />
                            <FieldError id="support-subject-error">
                                {errors.subject}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(errors.description)}>
                            <FieldLabel htmlFor="support-description">
                                Description
                            </FieldLabel>
                            <Textarea
                                id="support-description"
                                name="description"
                                required
                                rows={7}
                                maxLength={10000}
                                aria-invalid={Boolean(errors.description)}
                                aria-describedby={
                                    errors.description
                                        ? 'support-description-error'
                                        : undefined
                                }
                            />
                            <FieldError id="support-description-error">
                                {errors.description}
                            </FieldError>
                        </Field>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Submitting…' : 'Submit request'}
                        </Button>
                    </FieldGroup>
                )}
            </Form>
        </FormSheet>
    );
}

function TicketMenu({
    detail,
    onView,
}: {
    detail: TicketDetail | undefined;
    onView: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Ticket actions">
                    <MoreHorizontal aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>Ticket actions</DropdownMenuLabel>
                    <DropdownMenuItem onSelect={onView} disabled={!detail}>
                        <Eye aria-hidden="true" />
                        Open complete record
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function TicketSheet({
    teamSlug,
    ticket,
    open,
    onOpenChange,
    assignees,
    capabilities,
}: {
    teamSlug: string;
    ticket: TicketDetail | undefined;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assignees: SearchableSelectOption[];
    capabilities: Props['capabilities'];
}) {
    if (!ticket) {
        return null;
    }

    const isRequester = ticket.requester.id === capabilities.userId;
    const isAssignee = ticket.assignee?.id === capabilities.userId;
    const canUpload =
        ticket.status !== 'closed' &&
        (isRequester || isAssignee || capabilities.manage);
    const transitionOptions = availableTransitions(
        ticket,
        isRequester,
        isAssignee && capabilities.resolve,
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                <SheetHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{ticket.reference}</Badge>
                        <Badge variant="secondary">
                            {humanize(ticket.status)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(ticket.priority)} priority
                        </Badge>
                    </div>
                    <SheetTitle>{ticket.subject}</SheetTitle>
                    <SheetDescription>
                        Requested {formatDateTime(ticket.requestedAt)} by{' '}
                        {ticket.requester.name}.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-col gap-6 px-4 pb-8">
                    {ticket.county && <CountyIdentity county={ticket.county} />}
                    <Card>
                        <CardHeader>
                            <CardTitle>Request narrative</CardTitle>
                            <CardDescription>
                                {humanize(ticket.category)} ·{' '}
                                {humanize(ticket.channel)} channel
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm leading-6 whitespace-pre-wrap">
                                {ticket.description}
                            </p>
                        </CardContent>
                    </Card>

                    <SlaCard ticket={ticket} />

                    {capabilities.manage &&
                        ['open', 'triaged'].includes(ticket.status) && (
                            <ActionCard
                                title="Triage and assignment"
                                description="Assign an authorized resolver who is separate from the requester."
                            >
                                <Form
                                    {...assign.form({
                                        current_team: teamSlug,
                                        supportTicket: ticket.id,
                                    })}
                                >
                                    {({ errors, processing }) => (
                                        <FieldGroup>
                                            <SearchableSelect
                                                id={`support-assignee-${ticket.id}`}
                                                name="assigned_to"
                                                label="Support resolver"
                                                options={assignees.filter(
                                                    (option) =>
                                                        option.id !==
                                                        ticket.requester.id,
                                                )}
                                                error={errors.assigned_to}
                                            />
                                            <NarrativeField
                                                id={`assignment-${ticket.id}`}
                                                error={errors.narrative}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Assign resolver
                                            </Button>
                                        </FieldGroup>
                                    )}
                                </Form>
                            </ActionCard>
                        )}

                    {transitionOptions.length > 0 && (
                        <ActionCard
                            title="Workflow action"
                            description="All status changes are scope checked and appended to the immutable activity ledger."
                        >
                            <TransitionForm
                                teamSlug={teamSlug}
                                ticket={ticket}
                                options={transitionOptions}
                            />
                        </ActionCard>
                    )}

                    {canUpload && (
                        <ActionCard
                            title="Upload support record"
                            description="Scanned and born-digital records are privately stored, malware scanned, checksummed and sent for OCR when supported."
                        >
                            <DocumentUploadForm
                                teamSlug={teamSlug}
                                ticket={ticket}
                            />
                        </ActionCard>
                    )}

                    <section aria-labelledby="support-documents-heading">
                        <h2
                            id="support-documents-heading"
                            className="text-lg font-semibold"
                        >
                            Governed documents
                        </h2>
                        <div className="mt-3 flex flex-col gap-3">
                            {ticket.documents.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                                    No support records have been uploaded.
                                </p>
                            ) : (
                                ticket.documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-col justify-between gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {document.title}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {document.originalName ??
                                                    'Stored record'}{' '}
                                                ·{' '}
                                                {formatBytes(
                                                    document.sizeBytes,
                                                )}
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <Badge variant="outline">
                                                    Scan:{' '}
                                                    {humanize(
                                                        document.scanStatus,
                                                    )}
                                                </Badge>
                                                <Badge variant="outline">
                                                    OCR:{' '}
                                                    {humanize(
                                                        document.ocrStatus,
                                                    )}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={preview.url({
                                                        current_team: teamSlug,
                                                        document: document.id,
                                                    })}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Eye aria-hidden="true" />
                                                    Preview
                                                </a>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={download.url({
                                                        current_team: teamSlug,
                                                        document: document.id,
                                                    })}
                                                >
                                                    <Download aria-hidden="true" />
                                                    Download
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <Separator />
                    <section aria-labelledby="support-history-heading">
                        <h2
                            id="support-history-heading"
                            className="text-lg font-semibold"
                        >
                            Immutable activity history
                        </h2>
                        <ol className="mt-4 flex flex-col gap-4">
                            {[...ticket.activities]
                                .reverse()
                                .map((activity) => (
                                    <li
                                        key={activity.id}
                                        className="border-l-2 border-primary/30 pl-4"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {humanize(activity.type)}
                                            </p>
                                            <Badge variant="outline">
                                                {humanize(activity.toStatus)}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm">
                                            {activity.narrative}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {activity.actor} ·{' '}
                                            {formatDateTime(
                                                activity.occurredAt,
                                            )}{' '}
                                            · checksum{' '}
                                            {activity.checksum.slice(0, 12)}…
                                        </p>
                                    </li>
                                ))}
                        </ol>
                    </section>

                    <p className="text-xs text-muted-foreground">
                        Reference catalogue v{ticket.referenceData.version} ·{' '}
                        checksum {ticket.referenceData.checksum.slice(0, 16)}…
                    </p>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function SlaCard({ ticket }: { ticket: TicketDetail }) {
    const [observedAt] = useState(() => Date.now());
    const requested = new Date(ticket.requestedAt).getTime();
    const due = new Date(ticket.resolutionDueAt).getTime();
    const end = ticket.resolvedAt
        ? new Date(ticket.resolvedAt).getTime()
        : observedAt;
    const progress = Math.max(
        0,
        Math.min(100, ((end - requested) / (due - requested)) * 100),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Service-level targets</CardTitle>
                <CardDescription>
                    Provisional operational targets pending policy-owner
                    approval.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Progress
                    value={progress}
                    aria-label="Resolution SLA elapsed"
                />
                <div className="grid gap-3 text-sm sm:grid-cols-2">
                    <KeyValue
                        label="First response due"
                        value={formatDateTime(ticket.firstResponseDueAt)}
                    />
                    <KeyValue
                        label="First response"
                        value={
                            ticket.firstRespondedAt
                                ? formatDateTime(ticket.firstRespondedAt)
                                : 'Pending'
                        }
                    />
                    <KeyValue
                        label="Resolution due"
                        value={formatDateTime(ticket.resolutionDueAt)}
                    />
                    <KeyValue
                        label="Resolution"
                        value={
                            ticket.resolvedAt
                                ? formatDateTime(ticket.resolvedAt)
                                : 'Pending'
                        }
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function TransitionForm({
    teamSlug,
    ticket,
    options,
}: {
    teamSlug: string;
    ticket: TicketDetail;
    options: SearchableSelectOption[];
}) {
    const [selectedTransition, setSelectedTransition] = useState(
        options[0]?.id ?? '',
    );

    return (
        <Form
            {...transition.form({
                current_team: teamSlug,
                supportTicket: ticket.id,
            })}
        >
            {({ errors, processing }) => (
                <FieldGroup>
                    <SearchableSelect
                        id={`transition-${ticket.id}`}
                        name="transition"
                        label="Transition"
                        options={options}
                        value={selectedTransition}
                        onValueChange={setSelectedTransition}
                        error={errors.transition}
                    />
                    <NarrativeField
                        id={`transition-narrative-${ticket.id}`}
                        error={errors.narrative}
                    />
                    {selectedTransition === 'resolve' && (
                        <Field
                            data-invalid={Boolean(errors.resolution_summary)}
                        >
                            <FieldLabel
                                htmlFor={`resolution-summary-${ticket.id}`}
                            >
                                Resolution summary
                            </FieldLabel>
                            <Textarea
                                id={`resolution-summary-${ticket.id}`}
                                name="resolution_summary"
                                required
                                rows={5}
                                aria-invalid={Boolean(
                                    errors.resolution_summary,
                                )}
                            />
                            <FieldError>{errors.resolution_summary}</FieldError>
                        </Field>
                    )}
                    <Button type="submit" disabled={processing}>
                        Record workflow action
                    </Button>
                </FieldGroup>
            )}
        </Form>
    );
}

function DocumentUploadForm({
    teamSlug,
    ticket,
}: {
    teamSlug: string;
    ticket: TicketDetail;
}) {
    return (
        <Form
            {...storeSupportTicket.form({
                current_team: teamSlug,
                supportTicket: ticket.id,
            })}
            resetOnSuccess
        >
            {({ errors, processing, progress }) => (
                <FieldGroup>
                    <Field data-invalid={Boolean(errors.title)}>
                        <FieldLabel htmlFor={`document-title-${ticket.id}`}>
                            Record title
                        </FieldLabel>
                        <Input
                            id={`document-title-${ticket.id}`}
                            name="title"
                            required
                            aria-invalid={Boolean(errors.title)}
                        />
                        <FieldError>{errors.title}</FieldError>
                    </Field>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <SearchableSelect
                            id={`document-purpose-${ticket.id}`}
                            name="record_purpose"
                            label="Record purpose"
                            options={[
                                { id: 'request', name: 'Request evidence' },
                                {
                                    id: 'investigation',
                                    name: 'Investigation evidence',
                                },
                                {
                                    id: 'resolution',
                                    name: 'Resolution evidence',
                                },
                            ]}
                            error={errors.record_purpose}
                        />
                        <SearchableSelect
                            id={`document-source-${ticket.id}`}
                            name="source_type"
                            label="Document source"
                            options={[
                                { id: 'scanned', name: 'Scanned original' },
                                { id: 'digital', name: 'Born digital' },
                            ]}
                            error={errors.source_type}
                        />
                    </div>
                    <Field data-invalid={Boolean(errors.category)}>
                        <FieldLabel htmlFor={`document-category-${ticket.id}`}>
                            Records category
                        </FieldLabel>
                        <Input
                            id={`document-category-${ticket.id}`}
                            name="category"
                            required
                            defaultValue="service-desk-record"
                            aria-invalid={Boolean(errors.category)}
                        />
                        <FieldError>{errors.category}</FieldError>
                    </Field>
                    <Field data-invalid={Boolean(errors.document)}>
                        <FieldLabel htmlFor={`document-file-${ticket.id}`}>
                            File
                        </FieldLabel>
                        <Input
                            id={`document-file-${ticket.id}`}
                            name="document"
                            type="file"
                            required
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            aria-invalid={Boolean(errors.document)}
                        />
                        <FieldError>{errors.document}</FieldError>
                    </Field>
                    {progress && (
                        <Progress
                            value={progress.percentage}
                            aria-label="Document upload progress"
                        />
                    )}
                    <Button type="submit" disabled={processing}>
                        <FileUp aria-hidden="true" />
                        Upload governed record
                    </Button>
                </FieldGroup>
            )}
        </Form>
    );
}

function NarrativeField({ id, error }: { id: string; error?: string }) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>Action narrative</FieldLabel>
            <Textarea
                id={id}
                name="narrative"
                required
                rows={4}
                maxLength={5000}
                aria-invalid={Boolean(error)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function ActionCard({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function Metric({
    title,
    value,
    description,
    icon: Icon,
}: {
    title: string;
    value: number;
    description: string;
    icon: typeof Headphones;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardDescription>{title}</CardDescription>
                    <CardTitle className="mt-2 text-3xl">
                        {value.toLocaleString()}
                    </CardTitle>
                </div>
                <Icon className="text-muted-foreground" aria-hidden="true" />
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}

function KeyValue({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-muted/50 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}

function availableTransitions(
    ticket: TicketDetail,
    requester: boolean,
    resolver: boolean,
): SearchableSelectOption[] {
    const available: Record<string, SearchableSelectOption> = {
        start: { id: 'start', name: 'Start investigation' },
        request_information: {
            id: 'request_information',
            name: 'Request more information',
        },
        provide_information: {
            id: 'provide_information',
            name: 'Provide requested information',
        },
        resolve: { id: 'resolve', name: 'Submit resolution' },
        close: { id: 'close', name: 'Accept and close' },
        reopen: { id: 'reopen', name: 'Reject resolution and reopen' },
    };
    const keys = resolver
        ? ({
              triaged: ['start'],
              in_progress: ['request_information', 'resolve'],
          }[ticket.status] ?? [])
        : requester
          ? ({
                awaiting_requester: ['provide_information'],
                resolved: ['close', 'reopen'],
            }[ticket.status] ?? [])
          : [];

    return keys.map((key) => available[key]);
}

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-KE', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Africa/Nairobi',
    }).format(new Date(value));
}

function formatBytes(value: number): string {
    return new Intl.NumberFormat('en-KE', {
        style: 'unit',
        unit: value >= 1_000_000 ? 'megabyte' : 'kilobyte',
        unitDisplay: 'short',
        maximumFractionDigits: 1,
    }).format(value >= 1_000_000 ? value / 1_000_000 : value / 1_000);
}
